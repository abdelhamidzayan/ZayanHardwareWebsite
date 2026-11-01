<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AdminRosterService;
use App\Services\AuthService;
use App\Services\RateLimiter;

final class AdminUpdateController extends Controller
{
    private const UPDATE_SCRIPT = '/opt/lampp/htdocs/zayan_hardware/tools/update_local.py';
    private const PROJECT_DIR = '/opt/lampp/htdocs/zayan_hardware';

    public function index(): Response
    {
        if (!$this->isAdmin()) {
            return $this->redirect('/login');
        }

        $result = $_SESSION['admin_update_result'] ?? null;
        unset($_SESSION['admin_update_result']);

        return $this->view('admin/update', [
            'title' => 'تحديث الموقع',
            'active' => '',
            'updateResult' => is_array($result) ? $result : null,
        ]);
    }

    public function run(): Response
    {
        if (!$this->isAdmin()) {
            return $this->redirect('/login');
        }

        if (!Csrf::verify($this->request->input('_token'))) {
            return $this->fail('انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.');
        }

        $admin = AuthService::user();
        if (!$admin) return $this->redirect('/login');
        $adminId = (int) $admin['id'];
        if (!RateLimiter::allowForUser('admin.site_update', $adminId, 1, 3600, 3)) {
            App::security('admin.site_update.rate_limited', ['user_id' => $adminId]);
            return $this->fail('تم تشغيل التحديث مؤخرًا. انتظر قبل محاولة تشغيله مرة أخرى.');
        }

        $pdo = Database::connection();
        // Serialize the update with admin suspension, role changes, deletion
        // requests, and retention anonymization, which use this same lock.
        $lockAcquired = (int) $pdo->query(
            "SELECT GET_LOCK('zayan_hardware_admin_deletion_request', 5)"
        )->fetchColumn() === 1;
        if (!$lockAcquired) {
            App::security('admin.site_update.coordination_lock_unavailable', ['user_id' => $adminId]);
            return $this->fail('هناك عملية إدارية أخرى قيد التنفيذ. حاول مرة أخرى بعد انتهائها.');
        }

        try {
            return $this->executeUpdate($adminId, $pdo);
        } finally {
            try {
                $pdo->query("SELECT RELEASE_LOCK('zayan_hardware_admin_deletion_request')");
            } catch (\Throwable $lockError) {
                App::logException('WARNING', 'admin.site_update.coordination_lock_release_failed', $lockError, [
                    'user_id' => $adminId,
                ]);
            }
        }
    }

    private function executeUpdate(int $adminId, \PDO $pdo): Response
    {
        // Revalidate the actor against the database immediately before starting
        // the fixed, non-user-controlled update command.
        $pdo->beginTransaction();
        try {
            AdminRosterService::assertActorActive($pdo, $adminId);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($error instanceof \DomainException && $error->getMessage() === 'admin_not_active') {
                App::security('admin.site_update.actor_rejected', ['user_id' => $adminId]);
                return $this->redirect('/login');
            }
            throw $error;
        }

        App::audit('admin.site_update.started', ['user_id' => $adminId]);
        $startedAt = microtime(true);

        if (!function_exists('proc_open')) {
            App::audit('admin.site_update.completed', [
                'user_id' => $adminId,
                'exit_code' => null,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                'reason' => 'proc_open_unavailable',
            ]);
            return $this->fail('ميزة تشغيل أوامر النظام غير متاحة في إعدادات PHP.');
        }

        @set_time_limit(120);

        $command = [
            '/usr/bin/sudo',
            '-n',
            '-u',
            'a',
            '--',
            '/usr/bin/python3',
            self::UPDATE_SCRIPT,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ];

        $process = false;
        $output = '';
        $exitCode = 1;

        try {
            $process = proc_open($command, $descriptors, $pipes, self::PROJECT_DIR);

            if (!is_resource($process)) {
                App::audit('admin.site_update.completed', ['user_id' => $adminId, 'exit_code' => null, 'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2)]);
                return $this->fail('تعذر بدء عملية التحديث. راجع إعدادات تشغيل PHP.');
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $exitCode = proc_close($process);
        } catch (\Throwable $exception) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }

            App::logException('ERROR', 'admin.site_update_failed', $exception, ['user_id' => $adminId]);
            App::audit('admin.site_update.completed', [
                'user_id' => $adminId,
                'exit_code' => null,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
            return $this->fail('تعذر تشغيل عملية التحديث. راجع سجل التطبيق لمزيد من التفاصيل.');
        }

        App::audit('admin.site_update.completed', [
            'user_id' => $adminId,
            'exit_code' => $exitCode,
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        $output = trim($output);
        if (strlen($output) > 12000) {
            $output = substr($output, -12000);
            $output = "[تم اختصار بداية المخرجات]\n" . $output;
        }

        $_SESSION['admin_update_result'] = [
            'success' => $exitCode === 0,
            'output' => $output !== '' ? $output : 'لم تُرجع عملية التحديث تفاصيل.',
            'finishedAt' => date('Y-m-d H:i:s'),
        ];

        $this->flash(
            $exitCode === 0 ? 'success' : 'error',
            $exitCode === 0
                ? 'اكتملت عملية تحديث الموقع.'
                : 'فشل تحديث الموقع. راجع تفاصيل العملية بالأسفل.'
        );

        return $this->redirect('/admin/update');
    }

    private function isAdmin(): bool
    {
        $user = AuthService::user();
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    private function fail(string $message): Response
    {
        $this->flash('error', $message);
        return $this->redirect('/admin/update');
    }
}
