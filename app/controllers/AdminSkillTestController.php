<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AuthService;

final class AdminSkillTestController extends Controller
{
    public function index(): Response
    {
        $admin = AuthService::user();
        if (!$admin || ($admin['role'] ?? '') !== 'admin') return $this->redirect('/login');

        $pdo = Database::connection();
        $domains = $pdo->query('SELECT id, slug, name_ar FROM skill_domains WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
        $tests = $pdo->query(
            'SELECT t.id, t.title, t.version, t.pass_score, t.max_attempts, t.cooldown_minutes, t.is_active, d.name_ar AS skill_name
             FROM skill_tests t JOIN skill_domains d ON d.id = t.skill_domain_id
             ORDER BY t.created_at DESC'
        )->fetchAll();

        return $this->view('admin/skill-tests', [
            'title' => 'إدارة اختبارات المهارات',
            'active' => '',
            'domains' => $domains,
            'tests' => $tests,
            'errors' => [],
            'old' => [],
        ]);
    }

    public function create(): Response
    {
        $admin = AuthService::user();
        if (!$admin || ($admin['role'] ?? '') !== 'admin') return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/admin/skill-tests', 'انتهت صلاحية الصفحة.');

        $title = trim($this->text('title'));
        $domainId = filter_var($this->request->input('skill_domain_id'), FILTER_VALIDATE_INT);
        $version = trim($this->text('version')) ?: '1';
        $questionsJson = trim($this->text('questions_json'));
        $passScore = filter_var($this->request->input('pass_score'), FILTER_VALIDATE_FLOAT);
        $maxAttempts = filter_var($this->request->input('max_attempts'), FILTER_VALIDATE_INT);
        $cooldown = filter_var($this->request->input('cooldown_minutes'), FILTER_VALIDATE_INT);
        $questions = json_decode($questionsJson, true);

        if (mb_strlen($title) < 3 || mb_strlen($title) > 160
            || !$domainId || mb_strlen($version) > 30
            || $passScore === false || $passScore < 1 || $passScore > 100
            || $maxAttempts === false || $maxAttempts < 1 || $maxAttempts > 10
            || $cooldown === false || $cooldown < 1 || $cooldown > 10080
            || !is_array($questions) || count($questions) < 1 || count($questions) > 100) {
            return $this->fail('/admin/skill-tests', 'راجع العنوان والمجال ودرجة النجاح والمحاولات والانتظار وبنية الأسئلة.');
        }

        foreach ($questions as $question) {
            if (!is_array($question) || !isset($question['question'], $question['options'], $question['correct'])
                || !is_string($question['question']) || trim($question['question']) === ''
                || !is_array($question['options']) || count($question['options']) < 2
                || !is_string($question['correct'])
                || !array_key_exists($question['correct'], $question['options'])) {
                return $this->fail('/admin/skill-tests', 'كل سؤال يجب أن يحتوي question و options وخيار correct مطابقًا لأحد المفاتيح.');
            }
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT 1 FROM skill_domains WHERE id = ? AND is_active = 1');
        $stmt->execute([$domainId]);
        if (!$stmt->fetchColumn()) return $this->fail('/admin/skill-tests', 'المجال الفني غير موجود.');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO skill_tests (skill_domain_id, title, version, questions_json, pass_score, max_attempts, cooldown_minutes, is_active, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)'
            )->execute([
                $domainId, $title, $version,
                json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $passScore, $maxAttempts, $cooldown, (int) $admin['id'],
            ]);
            $testId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO security_audit_logs (actor_user_id, action, details_json, ip_hash)
                 VALUES (?, 'skill_test.created_draft', ?, ?)"
            )->execute([
                (int) $admin['id'],
                json_encode(['test_id' => $testId, 'title' => $title], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return $this->fail('/admin/skill-tests', 'تم حفظ الاختبار كمسودة غير متاحة للأعضاء حتى تفعيله من الإدارة.');
    }

    private function text(string $key): string
    {
        $value = $this->request->input($key);
        return is_string($value) ? $value : '';
    }

    private function fail(string $path, string $message): Response
    {
        $this->flash('info', $message);
        return $this->redirect($path);
    }
}
