<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Issue extends Model
{
    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT i.*, u.username, u.display_name AS author_name, u.role AS author_role,
                    p.specialization,
                    (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count,
                    (SELECT ir.id FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_id,
                    (SELECT ir.review_status FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_review,
                    (SELECT ir.was_repaired FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_was_repaired,
                    (SELECT ir.final_cause FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_final_cause,
                    (SELECT ir.repair_action FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_repair_action,
                    (SELECT ir.submitted_by FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_submitter
             FROM issues i
             JOIN users u ON u.id = i.user_id
             LEFT JOIN technician_profiles p ON p.user_id = u.id
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function replies(int $issueId, string $sort): array
    {
        $order = match ($sort) {
            'experts' => 'CASE WHEN u.role IN ("moderator", "admin") THEN 0 ELSE 1 END, helpful_count DESC, r.created_at DESC',
            'reputation' => 'reputation_points DESC, helpful_count DESC, r.created_at DESC',
            'helpful' => 'helpful_count DESC, r.created_at DESC',
            'newest' => 'r.created_at DESC',
            default => 'CASE WHEN r.id = (SELECT accepted_reply_id FROM issues WHERE id = ?) THEN 0 ELSE 1 END, helpful_count DESC, r.created_at DESC',
        };
        $sql = 'SELECT r.id, r.issue_id, r.user_id, r.body, r.created_at, r.updated_at,
                       u.username, u.display_name, u.role, p.specialization,
                       COALESCE((SELECT SUM(re.points) FROM reputation_events re WHERE re.user_id = u.id), 0) AS reputation_points,
                       (SELECT COUNT(*) FROM reply_votes v WHERE v.reply_id = r.id) AS helpful_count
                FROM issue_replies r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN technician_profiles p ON p.user_id = u.id
                WHERE r.issue_id = ? AND r.deleted_at IS NULL
                ORDER BY ' . $order . ' ';
        $stmt = self::db()->prepare($sql);
        if ($sort === 'relevant') {
            $stmt->execute([$issueId, $issueId]);
        } else {
            $stmt->execute([$issueId]);
        }
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public static function attachments(int $issueId): array
    {
        $stmt = self::db()->prepare('SELECT id, file_path, mime_type, file_size FROM issue_attachments WHERE issue_id = ? ORDER BY id');
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }

    public static function attachmentById(int $attachmentId): ?array
    {
        $stmt = self::db()->prepare('SELECT a.id, a.file_path, a.mime_type, a.file_size, a.issue_id FROM issue_attachments a WHERE a.id = ? LIMIT 1');
        $stmt->execute([$attachmentId]);
        return $stmt->fetch() ?: null;
    }

    public static function history(int $issueId): array
    {
        $stmt = self::db()->prepare(
            'SELECT h.previous_title, h.previous_description, h.previous_measurements, h.created_at,
                    u.display_name AS editor_name
             FROM issue_edit_history h JOIN users u ON u.id = h.editor_id
             WHERE h.issue_id = ? ORDER BY h.created_at DESC LIMIT 20'
        );
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }

    public static function addReply(int $issueId, int $userId, string $body): void
    {
        $stmt = self::db()->prepare("INSERT INTO issue_replies (issue_id, user_id, body) SELECT ?, ?, ? FROM issues WHERE id = ? AND status = 'open'");
        $stmt->execute([$issueId, $userId, $body, $issueId]);
        if ($stmt->rowCount() !== 1) {
            throw new \DomainException('discussion_closed');
        }
    }

    public static function edit(int $issueId, int $userId, string $title, string $description, string $measurements): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT title, description, measurements FROM issues WHERE id = ? AND user_id = ? AND status = 'open' FOR UPDATE");
            $stmt->execute([$issueId, $userId]);
            $old = $stmt->fetch();
            if (!$old) {
                throw new \DomainException('not_editable');
            }
            $pdo->prepare(
                'INSERT INTO issue_edit_history (issue_id, editor_id, previous_title, previous_description, previous_measurements)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$issueId, $userId, $old['title'], $old['description'], $old['measurements']]);
            $pdo->prepare('UPDATE issues SET title = ?, description = ?, measurements = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$title, $description, $measurements !== '' ? $measurements : null, $issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function vote(int $replyId, int $userId): void
    {
        $pdo = self::db();
        $stmt = $pdo->prepare('SELECT user_id FROM issue_replies WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$replyId]);
        $owner = $stmt->fetchColumn();
        if ($owner === false || (int) $owner === $userId) {
            throw new \DomainException('invalid_vote');
        }
        $stmt = $pdo->prepare('SELECT id FROM reply_votes WHERE reply_id = ? AND user_id = ?');
        $stmt->execute([$replyId, $userId]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('DELETE FROM reply_votes WHERE reply_id = ? AND user_id = ?')->execute([$replyId, $userId]);
        } else {
            $pdo->prepare("INSERT INTO reply_votes (reply_id, user_id, vote_type) VALUES (?, ?, 'helpful')")
                ->execute([$replyId, $userId]);
        }
    }

    public static function accept(int $issueId, int $replyId, int $ownerId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM issues WHERE id = ? AND user_id = ? AND status = 'open' FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('not_owner_or_closed');
            $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND deleted_at IS NULL');
            $stmt->execute([$replyId, $issueId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('reply_not_in_issue');
            $pdo->prepare('UPDATE issues SET accepted_reply_id = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$replyId, $issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }



    public static function reviewResult(int $issueId, int $resultId, int $reviewerId, bool $approve): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, submitted_by, review_status FROM issue_results WHERE id = ? AND issue_id = ? FOR UPDATE');
            $stmt->execute([$resultId, $issueId]);
            $result = $stmt->fetch();
            if (!$result || $result['review_status'] !== 'pending') {
                throw new \DomainException('result_not_pending');
            }
            $status = $approve ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE issue_results SET review_status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$status, $reviewerId, $resultId]);
            if ($approve) {
                $pdo->prepare(
                    "INSERT IGNORE INTO reputation_events (user_id, event_key, points, reason) VALUES (?, ?, 5, 'Approved helpful issue result')"
                )->execute([(int) $result['submitted_by'], 'issue_result:' . $resultId . ':approved']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function reopen(int $issueId, int $ownerId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT status FROM issues WHERE id = ? AND user_id = ? FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            $status = $stmt->fetchColumn();
            if ($status === false || $status === 'open') {
                throw new \DomainException('not_reopenable');
            }
            $stmt = $pdo->prepare("SELECT review_status FROM issue_results WHERE issue_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$issueId]);
            $review = $stmt->fetchColumn();
            if ($review === false || $review === 'pending') {
                throw new \DomainException('result_pending_review');
            }
            $pdo->prepare("UPDATE issues SET status = 'open', closed_at = NULL, updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function closeWithResult(int $issueId, int $ownerId, bool $repaired, string $cause, string $action, ?int $helpfulReplyId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM issues WHERE id = ? AND user_id = ? AND status = 'open' FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('not_owner_or_closed');
            if ($helpfulReplyId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND deleted_at IS NULL');
                $stmt->execute([$helpfulReplyId, $issueId]);
                if (!$stmt->fetchColumn()) throw new \DomainException('reply_not_in_issue');
            }
            $pdo->prepare(
                'INSERT INTO issue_results (issue_id, submitted_by, was_repaired, final_cause, repair_action, helpful_reply_id)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$issueId, $ownerId, $repaired ? 1 : 0, $cause, $repaired ? $action : null, $helpfulReplyId]);
            $status = $repaired ? 'resolved' : 'closed_unresolved';
            $pdo->prepare('UPDATE issues SET status = ?, closed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$status, $issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
