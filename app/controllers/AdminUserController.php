<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AuthService;

final class AdminUserController extends Controller
{
    public function index(): Response
    {
        if (!$this->adminOrNull()) return $this->redirect('/login');

        $stmt = Database::connection()->query(
            "SELECT u.id, u.username, u.display_name, u.email, u.role, u.account_status, u.created_at,
                    p.phone,
                    (SELECT MAX(d.scheduled_purge_at) FROM account_deletion_requests d
                     WHERE d.user_id = u.id AND d.status = 'pending') AS scheduled_purge_at
             FROM users u LEFT JOIN technician_profiles p ON p.user_id = u.id
             ORDER BY u.created_at DESC LIMIT 200"
        );

        return $this->view('admin/users', [
            'title' => 'إدارة الحسابات',
            'active' => '',
            'users' => $stmt->fetchAll(),
        ]);
    }

    public function resetPassword(int $id): Response
    {
        $admin = $this->adminOrNull();
        if (!$admin) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/admin/users', 'انتهت صلاحية الصفحة.');

        $password = $this->text('password');
        $note = trim($this->text('note'));
        if (strlen($password) < 12 || strlen($password) > 72) {
            return $this->fail('/admin/users', 'كلمة المرور المؤقتة يجب أن تكون من 12 إلى 72 بايت.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND account_status <> 'deleted'");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            if ($stmt->rowCount() < 1) throw new \DomainException('user_not_found');

            $pdo->prepare('INSERT INTO admin_password_resets (user_id, admin_id, note) VALUES (?, ?, ?)')
                ->execute([$id, (int) $admin['id'], $note !== '' ? mb_substr($note, 0, 255) : null]);

            $pdo->prepare(
                "INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (?, ?, 'account.password_reset_by_admin', ?, ?)"
            )->execute([
                (int) $admin['id'], $id,
                json_encode(['note' => mb_substr($note, 0, 255)], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \DomainException) return $this->fail('/admin/users', 'الحساب غير موجود أو محذوف نهائيًا.');
            throw $e;
        }

        return $this->fail('/admin/users', 'تم تغيير كلمة المرور وتسجيل العملية في سجل الإدارة.');
    }

    public function setStatus(int $id): Response
    {
        $admin = $this->adminOrNull();
        if (!$admin) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/admin/users', 'انتهت صلاحية الصفحة.');
        if ((int) $admin['id'] === $id) return $this->fail('/admin/users', 'لا يمكنك تغيير حالة حسابك من هذه الصفحة.');

        $status = $this->text('status');
        if (!in_array($status, ['active', 'suspended', 'banned'], true)) {
            return $this->fail('/admin/users', 'حالة الحساب غير مسموحة.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ? AND account_status <> 'deleted'");
            $stmt->execute([$status, $id]);
            if ($stmt->rowCount() < 1) throw new \DomainException('user_not_found_or_unchanged');

            if ($status === 'active') {
                $pdo->prepare("UPDATE account_deletion_requests SET status = 'cancelled', handled_by = ?, note = 'Cancelled by admin' WHERE user_id = ? AND status = 'pending'")
                    ->execute([(int) $admin['id'], $id]);
            }

            $pdo->prepare(
                'INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                (int) $admin['id'], $id, 'account.status_changed',
                json_encode(['status' => $status], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \DomainException) return $this->fail('/admin/users', 'الحساب غير موجود أو لم تتغير حالته.');
            throw $e;
        }

        return $this->fail('/admin/users', 'تم تحديث حالة الحساب وتسجيل العملية.');
    }

    public function setRole(int $id): Response
    {
        $admin = $this->adminOrNull();
        if (!$admin) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) return $this->fail('/admin/users', 'انتهت صلاحية الصفحة.');
        if ((int) $admin['id'] === $id) return $this->fail('/admin/users', 'لا يمكنك تغيير دور حسابك من هذه الصفحة.');

        $role = $this->text('role');
        if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
            return $this->fail('/admin/users', 'الدور المطلوب غير مسموح.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? AND account_status <> \'deleted\' FOR UPDATE');
            $stmt->execute([$id]);
            $oldRole = $stmt->fetchColumn();
            if ($oldRole === false) throw new \DomainException('user_not_found');

            if ($oldRole === 'admin' && $role !== 'admin') {
                $admins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND account_status = 'active'")->fetchColumn();
                if ($admins <= 1) throw new \DomainException('last_admin');
            }

            $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            $pdo->prepare(
                'INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                (int) $admin['id'], $id, 'account.role_changed',
                json_encode(['from' => $oldRole, 'to' => $role], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \DomainException) {
                return $this->fail('/admin/users', $e->getMessage() === 'last_admin'
                    ? 'لا يمكن سحب صلاحية آخر مدير نشط.'
                    : 'الحساب غير موجود أو محذوف نهائيًا.');
            }
            throw $e;
        }

        return $this->fail('/admin/users', 'تم تحديث دور الحساب وتسجيل العملية.');
    }

    private function adminOrNull(): ?array
    {
        $user = AuthService::user();
        return $user !== null && ($user['role'] ?? '') === 'admin' ? $user : null;
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
