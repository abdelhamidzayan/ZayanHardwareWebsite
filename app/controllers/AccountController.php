<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AuthService;

final class AccountController extends Controller
{
    public function requestDeletion(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) {
            $this->flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
            return $this->redirect('/feed');
        }

        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $pdo = Database::connection();
        $adminDeletionLockAcquired = false;
        try {
            // Acquire the shared roster lock before any per-user row lock.
            $adminLock = (int) $pdo->query(
                "SELECT GET_LOCK('zayan_hardware_admin_deletion_request', 5)"
            )->fetchColumn();
            if ($adminLock !== 1) {
                throw new \RuntimeException('Could not acquire the account-deletion coordination lock.');
            }
            $adminDeletionLockAcquired = true;
            $pdo->beginTransaction();
            // Serialize concurrent deletion requests on the existing user row.
            $accountLock = $pdo->prepare('SELECT account_status, role FROM users WHERE id = ? FOR UPDATE');
            $accountLock->execute([(int) $user['id']]);
            $account = $accountLock->fetch(\PDO::FETCH_ASSOC);
            if (!$account || $account['account_status'] !== 'active') {
                $pdo->rollBack();
                $this->flash('info', 'طلب إيقاف الحساب أو حذفه مسجل بالفعل.');
                return $this->redirect('/');
            }

            // Serialize administrator deletion requests so two admins cannot
            // simultaneously schedule the deletion of the last active admin.
            if ($account['role'] === 'admin') {
                $otherAdmins = $pdo->prepare(
                    "SELECT COUNT(*) FROM users
                     WHERE role = 'admin' AND account_status = 'active' AND id <> ?"
                );
                $otherAdmins->execute([(int) $user['id']]);
                if ((int) $otherAdmins->fetchColumn() < 1) {
                    $pdo->rollBack();
                    $this->flash('error', 'قبل طلب حذف حساب المدير، يجب تعيين مدير نشط آخر.');
                    return $this->redirect('/');
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO account_deletion_requests (user_id, status, scheduled_purge_at)
                 VALUES (?, 'pending', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))"
            );
            $stmt->execute([(int) $user['id']]);
            $pdo->prepare(
                "UPDATE users SET account_status = 'deletion_pending', auth_version = auth_version + 1
                 WHERE id = ? AND account_status = 'active'"
            )->execute([(int) $user['id']]);
            $pdo->prepare(
                "INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (?, ?, 'account.deletion_requested', ?, ?)"
            )->execute([
                (int) $user['id'],
                (int) $user['id'],
                json_encode([
                    'retention_days' => 30,
                    'after_retention' => 'identity_anonymization',
                    'technical_content' => 'retained',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        } finally {
            if ($adminDeletionLockAcquired) {
                $pdo->query("SELECT RELEASE_LOCK('zayan_hardware_admin_deletion_request')");
            }
        }

        AuthService::logout();
        $this->flash('success', 'تم إيقاف الحساب وتسجيل طلب إخفاء بياناته الشخصية بعد 30 يومًا. منشوراتك الفنية وردودك ومخططاتك ومرفقاتك وسجل التشخيص ستظل محفوظة باسم «عضو محذوف».');
        return $this->redirect('/');
    }

}
