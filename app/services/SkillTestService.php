<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Database;

final class SkillTestService
{
    public static function activeTests(): array
    {
        $stmt = Database::connection()->query(
            'SELECT t.id, t.title, t.pass_score, t.max_attempts, t.cooldown_minutes,
                    d.name_ar AS skill_name
             FROM skill_tests t
             JOIN skill_domains d ON d.id = t.skill_domain_id AND d.is_active = 1
             WHERE t.is_active = 1
             ORDER BY d.sort_order, t.title'
        );
        return $stmt->fetchAll();
    }

    public static function findActive(int $testId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.id, t.skill_domain_id, t.title, t.version, t.questions_json,
                    t.pass_score, t.max_attempts, t.cooldown_minutes, d.name_ar AS skill_name
             FROM skill_tests t
             JOIN skill_domains d ON d.id = t.skill_domain_id AND d.is_active = 1
             WHERE t.id = ? AND t.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$testId]);
        $test = $stmt->fetch();
        if (!$test) return null;

        $questionsJson = (string) $test['questions_json'];
        if (strlen($questionsJson) > 262144) return null;
        $questions = json_decode($questionsJson, true);
        if (!is_array($questions) || !array_is_list($questions) || !$questions
            || count($questions) > 100) {
            return null;
        }

        // Build a strict public DTO. Never return answer keys, explanations,
        // or any arbitrary admin-supplied fields to the browser.
        $publicQuestions = [];
        foreach ($questions as $index => $question) {
            if (!is_array($question)
                || !isset($question['question'], $question['correct'], $question['options'])
                || !is_string($question['question'])
                || trim($question['question']) === ''
                || mb_strlen($question['question']) > 2000
                || !is_string($question['correct'])
                || strlen($question['correct']) < 1
                || strlen($question['correct']) > 80
                || !is_array($question['options'])
                || count($question['options']) < 2
                || count($question['options']) > 10
                || !array_key_exists($question['correct'], $question['options'])) {
                return null;
            }

            $publicOptions = [];
            foreach ($question['options'] as $optionKey => $optionLabel) {
                $key = (string) $optionKey;
                if ($key === '' || strlen($key) > 80
                    || !is_string($optionLabel) || trim($optionLabel) === ''
                    || mb_strlen($optionLabel) > 300) {
                    return null;
                }
                $publicOptions[$key] = trim($optionLabel);
            }

            $publicQuestions[] = [
                'question' => trim($question['question']),
                'options' => $publicOptions,
                'index' => $index,
            ];
        }
        $test['questions'] = $publicQuestions;
        unset($test['questions_json']);

        return $test;
    }

    public static function attemptState(int $userId, int $testId, int $maxAttempts, int $cooldownMinutes): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS attempts_used, MAX(started_at) AS last_attempt_at
             FROM skill_test_attempts WHERE user_id = ? AND skill_test_id = ?'
        );
        $stmt->execute([$userId, $testId]);
        $row = $stmt->fetch() ?: ['attempts_used' => 0, 'last_attempt_at' => null];
        $used = (int) $row['attempts_used'];
        $wait = 0;
        if ($row['last_attempt_at'] !== null) {
            $waitStmt = $pdo->prepare(
                'SELECT GREATEST(0, ? - TIMESTAMPDIFF(MINUTE, ?, UTC_TIMESTAMP()))'
            );
            $waitStmt->execute([$cooldownMinutes, $row['last_attempt_at']]);
            $wait = (int) $waitStmt->fetchColumn();
        }

        return [
            'attempts_used' => $used,
            'attempts_remaining' => max(0, $maxAttempts - $used),
            'wait_minutes' => $wait,
            'can_attempt' => $used < $maxAttempts && $wait === 0,
        ];
    }

    public static function submit(int $userId, int $testId, array $answers): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.skill_domain_id, t.questions_json, t.pass_score, t.max_attempts, t.cooldown_minutes
                 FROM skill_tests t
                 INNER JOIN skill_domains d ON d.id = t.skill_domain_id AND d.is_active = 1
                 WHERE t.id = ? AND t.is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$testId]);
            $test = $stmt->fetch();
            if (!$test) throw new \DomainException('test_unavailable');

            $state = self::attemptState($userId, $testId, (int) $test['max_attempts'], (int) $test['cooldown_minutes']);
            if ($state['attempts_remaining'] < 1) throw new \DomainException('max_attempts');
            if ($state['wait_minutes'] > 0) throw new \DomainException('cooldown');

            $questionsJson = (string) $test['questions_json'];
            if (strlen($questionsJson) > 262144) throw new \DomainException('invalid_test');
            $questions = json_decode($questionsJson, true);
            if (!is_array($questions) || !array_is_list($questions) || !$questions
                || count($questions) > 100) {
                throw new \DomainException('invalid_test');
            }

            $correctCount = 0;
            $total = count($questions);
            $safeAnswers = [];
            foreach ($questions as $index => $question) {
                if (!is_array($question)
                    || !isset($question['question'], $question['correct'], $question['options'])
                    || !is_string($question['question'])
                    || trim($question['question']) === ''
                    || mb_strlen($question['question']) > 2000
                    || !is_string($question['correct'])
                    || strlen($question['correct']) < 1
                    || strlen($question['correct']) > 80
                    || !is_array($question['options'])
                    || count($question['options']) < 2
                    || count($question['options']) > 10
                    || !array_key_exists($question['correct'], $question['options'])) {
                    throw new \DomainException('invalid_test');
                }

                foreach ($question['options'] as $optionKey => $optionLabel) {
                    $key = (string) $optionKey;
                    if ($key === '' || strlen($key) > 80
                        || !is_string($optionLabel) || trim($optionLabel) === ''
                        || mb_strlen($optionLabel) > 300) {
                        throw new \DomainException('invalid_test');
                    }
                }

                $answer = $answers[(string) $index] ?? $answers[$index] ?? '';
                // Accept only a bounded key that belongs to this question's
                // option set. Arbitrary POST strings must not be persisted.
                if (!is_string($answer) || strlen($answer) > 80
                    || !array_key_exists($answer, $question['options'])) {
                    $answer = '';
                }
                $safeAnswers[(string) $index] = $answer;
                if ($answer !== '' && hash_equals($question['correct'], $answer)) $correctCount++;
            }

            $score = round(($correctCount / $total) * 100, 2);
            $passed = $score >= (float) $test['pass_score'];
            $attemptNumber = $state['attempts_used'] + 1;
            $pdo->prepare(
                'INSERT INTO skill_test_attempts
                 (user_id, skill_test_id, attempt_number, answers_json, score, passed, started_at, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            )->execute([
                $userId, $testId, $attemptNumber,
                json_encode($safeAnswers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $score, $passed ? 1 : 0,
            ]);

            // Failed attempts are recorded in skill_test_attempts, but must never
            // downgrade a previously approved skill rating.
            if ($passed) {
                $pdo->prepare(
                    'INSERT INTO user_skill_ratings (user_id, skill_domain_id, rating, approved_assessments)
                     VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE rating = VALUES(rating),
                        approved_assessments = approved_assessments + 1,
                        updated_at = UTC_TIMESTAMP()'
                )->execute([$userId, (int) $test['skill_domain_id'], $score]);
            }

            $pdo->commit();
            return ['score' => $score, 'passed' => $passed, 'correct' => $correctCount, 'total' => $total];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
