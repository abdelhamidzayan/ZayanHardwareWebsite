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
             JOIN skill_domains d ON d.id = t.skill_domain_id
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
             JOIN skill_domains d ON d.id = t.skill_domain_id
             WHERE t.id = ? AND t.is_active = 1 LIMIT 1'
        );
        $stmt->execute([$testId]);
        $test = $stmt->fetch();
        if (!$test) return null;

        $questions = json_decode((string) $test['questions_json'], true);
        if (!is_array($questions) || !$questions) return null;

        foreach ($questions as $index => &$question) {
            if (!is_array($question) || !isset($question['question'], $question['options']) || !is_array($question['options'])) {
                return null;
            }
            unset($question['correct'], $question['answer'], $question['explanation']);
            $question['index'] = $index;
        }
        unset($question);
        $test['questions'] = $questions;
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
                'SELECT id, skill_domain_id, questions_json, pass_score, max_attempts, cooldown_minutes
                 FROM skill_tests WHERE id = ? AND is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$testId]);
            $test = $stmt->fetch();
            if (!$test) throw new \DomainException('test_unavailable');

            $state = self::attemptState($userId, $testId, (int) $test['max_attempts'], (int) $test['cooldown_minutes']);
            if ($state['attempts_remaining'] < 1) throw new \DomainException('max_attempts');
            if ($state['wait_minutes'] > 0) throw new \DomainException('cooldown');

            $questions = json_decode((string) $test['questions_json'], true);
            if (!is_array($questions) || !$questions) throw new \DomainException('invalid_test');

            $correctCount = 0;
            $total = count($questions);
            $safeAnswers = [];
            foreach ($questions as $index => $question) {
                if (!is_array($question) || !isset($question['correct'])) throw new \DomainException('invalid_test');
                $answer = $answers[(string) $index] ?? $answers[$index] ?? '';
                if (!is_string($answer)) $answer = '';
                $safeAnswers[(string) $index] = $answer;
                if ($answer !== '' && hash_equals((string) $question['correct'], $answer)) $correctCount++;
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
