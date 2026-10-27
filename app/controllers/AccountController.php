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
            return $this->redirect('/profile/' . $this->username());
        }

        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO account_deletion_requests (user_id, status, scheduled_purge_at)
                 VALUES (?, 'pending', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))"
            );
            $stmt->execute([(int) $user['id']]);
            $pdo->prepare("UPDATE users SET account_status = 'deletion_pending' WHERE id = ?")
                ->execute([(int) $user['id']]);
            $pdo->prepare(
                "INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (?, ?, 'account.deletion_requested', ?, ?)"
            )->execute([
                (int) $user['id'],
                (int) $user['id'],
                json_encode(['retention_days' => 30], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        AuthService::logout();
        $this->flash('success', 'تم إخفاء الحساب وتعطيله. ستحتفظ المنصة بالبيانات لمدة 30 يومًا قبل أي حذف نهائي.');
        return $this->redirect('/');
    }

    private function username(): string
    {
        $user = AuthService::user();
        return is_array($user) ? (string) $user['username'] : '';
    }
}
