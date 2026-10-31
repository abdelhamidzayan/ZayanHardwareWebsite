<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\Database;
use App\Services\AuthService;
use App\Services\AdminRosterService;

final class AdminFeaturedPostsController extends Controller
{
    public function index(): Response
    {
        if (!$this->isAdmin()) return $this->redirect('/login');

        $pdo = Database::connection();
        $issues = $pdo->query(
            "SELECT i.id, i.title, i.device_model, i.category, i.description, i.status, i.created_at,
                    u.display_name AS author_name,
                    (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count,
                    (fp.issue_id IS NOT NULL) AS is_featured
             FROM issues i
             JOIN users u ON u.id = i.user_id
             LEFT JOIN featured_home_issues fp ON fp.issue_id = i.id
             WHERE i.deleted_at IS NULL
             ORDER BY i.created_at DESC
             LIMIT 200"
        )->fetchAll();

        $featuredCount = (int) $pdo->query('SELECT COUNT(*) FROM featured_home_issues')->fetchColumn();

        return $this->view('admin/featured-posts', [
            'title' => 'إدارة منشورات الرئيسية',
            'active' => '',
            'issues' => $issues,
            'featuredCount' => $featuredCount,
        ]);
    }

    public function update(int $id): Response
    {
        if (!$this->isAdmin()) return $this->redirect('/login');
        if (!Csrf::verify($this->request->input('_token'))) {
            return $this->fail('/admin', 'انتهت صلاحية الصفحة. حدّثها وحاول مرة أخرى.');
        }

        $action = $this->request->input('action');
        if (!in_array($action, ['feature', 'unfeature'], true)) {
            return $this->fail('/admin', 'الإجراء المطلوب غير صالح.');
        }

        $user = AuthService::user();
        if (!$user) return $this->redirect('/login');

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Revalidate the administrator while holding the user row lock, and
            // serialize the feature decision against concurrent post deletion.
            AdminRosterService::assertActorActive($pdo, (int) $user['id']);
            $check = $pdo->prepare('SELECT user_id FROM issues WHERE id = ? AND deleted_at IS NULL FOR UPDATE');
            $check->execute([$id]);
            $issueOwnerId = $check->fetchColumn();
            if ($issueOwnerId === false) throw new \\DomainException('issue_not_available');

            if ($action === 'feature') {
                $stmt = $pdo->prepare(
                    'INSERT IGNORE INTO featured_home_issues (issue_id, selected_by) VALUES (?, ?)'
                );
                $stmt->execute([$id, (int) $user['id']]);
                $changed = $stmt->rowCount() > 0;
                $auditAction = 'issue.featured';
            } else {
                $stmt = $pdo->prepare('DELETE FROM featured_home_issues WHERE issue_id = ?');
                $stmt->execute([$id]);
                $changed = $stmt->rowCount() > 0;
                $auditAction = 'issue.unfeatured';
            }

            if ($changed) {
                $pdo->prepare(
                    'INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    (int) $user['id'],
                    (int) $issueOwnerId,
                    $auditAction,
                    json_encode(['issue_id' => $id], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
                ]);
            }
            $pdo->commit();
        } catch (\\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($error instanceof \\DomainException) {
                return $this->fail('/admin', $error->getMessage() === 'admin_not_active'
                    ? 'لم تعد تملك صلاحية إدارة المنشورات.'
                    : 'المنشور غير موجود أو لم يعد متاحًا.');
            }
            throw $error;
        }

        $message = $action === 'feature'
            ? ($changed ? 'تم اختيار المنشور للظهور في كروت الرئيسية.' : 'المنشور موجود بالفعل ضمن الاختيارات.')
            : ($changed ? 'تمت إزالة المنشور من اختيارات الرئيسية.' : 'المنشور لم يكن ضمن الاختيارات.');
        return $this->fail('/admin', $message);
    }

    private function isAdmin(): bool
    {
        $user = AuthService::user();
        return $user !== null && ($user['role'] ?? '') === 'admin';
    }

    private function fail(string $path, string $message): Response
    {
        $this->flash('info', $message);
        return $this->redirect($path);
    }
}
