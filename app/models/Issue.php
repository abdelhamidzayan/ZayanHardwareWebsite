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
                    (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count,
                    (SELECT ir.id FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_id,
                    (SELECT ir.review_status FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_review,
                    (SELECT ir.was_repaired FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_was_repaired,
                    (SELECT ir.final_cause FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_final_cause,
                    (SELECT ir.repair_action FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_repair_action,
                    (SELECT ir.submitted_by FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_submitter
             FROM issues i
             JOIN users u ON u.id = i.user_id
             WHERE i.id = ? AND i.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Fetch real help requests for the feed; demo fixtures must not be shown to members. */
    public static function feed(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = self::db()->prepare(
            'SELECT i.id, i.title, i.content_type, i.device_model AS device, i.category, i.description,
                    i.status, i.created_at, u.display_name AS author, u.role,
                    (SELECT COUNT(*) FROM issue_replies r
                     WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS comments
             FROM issues i
              JOIN users u ON u.id = i.user_id
              WHERE i.deleted_at IS NULL
              ORDER BY i.created_at DESC, i.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Create one of the supported post types while keeping existing issue IDs and relations intact. */
    public static function create(int $userId, string $type, string $title, ?string $deviceModel, ?string $category, string $description, string $measurements): int
    {
        $stmt = self::db()->prepare(
            "INSERT INTO issues (user_id, content_type, title, device_model, category, description, measurements, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'open')"
        );
        $stmt->execute([
            $userId,
            $type,
            $title,
            $deviceModel !== '' ? $deviceModel : null,
            $category !== '' ? $category : null,
            $description,
            $measurements !== '' ? $measurements : null,
        ]);
        return (int) self::db()->lastInsertId();
    }

    public static function addAttachment(int $issueId, int $userId, string $filePath, string $mimeType, int $fileSize, bool $isInline = false): int
    {
        $stmt = self::db()->prepare(
            'INSERT INTO issue_attachments (issue_id, uploaded_by, file_path, mime_type, file_size, is_inline)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$issueId, $userId, $filePath, $mimeType, $fileSize, $isInline ? 1 : 0]);
        return (int) self::db()->lastInsertId();
    }

    public static function updateDescriptionContent(int $issueId, int $userId, string $body): void
    {
        self::db()->prepare('UPDATE issues SET description = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?')
            ->execute([$body, $issueId, $userId]);
    }

    public static function updateReplyContent(int $replyId, int $userId, string $body): void
    {
        self::db()->prepare('UPDATE issue_replies SET body = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND deleted_at IS NULL')
            ->execute([$body, $replyId, $userId]);
    }

    public static function updateDiagnosticUpdateContent(int $updateId, int $userId, string $body): void
    {
        self::db()->prepare('UPDATE issue_diagnostic_updates SET body = ?, updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ? AND deleted_at IS NULL')
            ->execute([$body, $updateId, $userId]);
    }

    public static function allInlineContent(int $issueId): array
    {
        $stmt = self::db()->prepare(
            'SELECT description AS body FROM issues WHERE id = ?
             UNION ALL SELECT body FROM issue_replies WHERE issue_id = ? AND deleted_at IS NULL
             UNION ALL SELECT body FROM issue_diagnostic_updates WHERE issue_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$issueId, $issueId, $issueId]);
        return array_map(static fn (array $row): string => (string) $row['body'], $stmt->fetchAll());
    }

    public static function inlineAttachments(int $issueId): array
    {
        $stmt = self::db()->prepare('SELECT id, file_path FROM issue_attachments WHERE issue_id = ? AND is_inline = 1');
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }

    public static function deleteInlineAttachment(int $issueId, int $attachmentId): void
    {
        self::db()->prepare('DELETE FROM issue_attachments WHERE id = ? AND issue_id = ? AND is_inline = 1')
            ->execute([$attachmentId, $issueId]);
    }

    public static function workbench(int $userId): array
    {
        $stmt = self::db()->prepare(
            "SELECT i.id, i.title, i.device_model, i.category, i.status, i.created_at, i.updated_at,
                    i.accepted_reply_id,
                    (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count,
                    (SELECT COUNT(*) FROM issue_diagnostic_updates d WHERE d.issue_id = i.id) AS diagnostic_count,
                    (SELECT ir.review_status FROM issue_results ir WHERE ir.issue_id = i.id ORDER BY ir.created_at DESC, ir.id DESC LIMIT 1) AS latest_result_review
             FROM issues i
             WHERE i.user_id = ? AND i.content_type = 'help_request' AND i.deleted_at IS NULL
             ORDER BY CASE WHEN i.status IN ('open','in_progress','awaiting_confirmation') THEN 0 ELSE 1 END,
                      i.updated_at DESC, i.id DESC
             LIMIT 100"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function myPosts(int $userId, string $type = '', string $status = ''): array
    {
        $allowedTypes = ['help_request', 'technical_post', 'discussion'];
        $allowedStatuses = ['open', 'in_progress', 'awaiting_confirmation', 'resolved', 'closed_unresolved'];
        $where = ['i.user_id = ?', 'i.deleted_at IS NULL'];
        $params = [$userId];
        if (in_array($type, $allowedTypes, true)) {
            $where[] = 'i.content_type = ?';
            $params[] = $type;
        }
        if (in_array($status, $allowedStatuses, true)) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }
        $sql = 'SELECT i.id, i.title, i.content_type, i.device_model, i.category, i.status,
                       i.created_at, i.updated_at,
                       (SELECT COUNT(*) FROM issue_replies r WHERE r.issue_id = i.id AND r.deleted_at IS NULL) AS reply_count,
                       (SELECT COUNT(*) FROM issue_diagnostic_updates d WHERE d.issue_id = i.id) AS diagnostic_count
                FROM issues i WHERE ' . implode(' AND ', $where) . '
                ORDER BY i.updated_at DESC, i.id DESC LIMIT 100';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function deleteIssue(int $issueId, int $ownerId): void
    {
        $stmt = self::db()->prepare(
            'UPDATE issues SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$issueId, $ownerId]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('not_owner_or_already_deleted');
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
        $sql = 'SELECT r.id, r.issue_id, r.user_id, r.parent_reply_id, r.body, r.created_at, r.updated_at,
                       u.username, u.display_name, u.role,
                       parent_user.display_name AS parent_author_name, parent.user_id AS parent_author_id,
                       COALESCE((SELECT SUM(re.points) FROM reputation_events re WHERE re.user_id = u.id), 0) AS reputation_points,
                       (SELECT COUNT(*) FROM reply_votes v WHERE v.reply_id = r.id) AS helpful_count
                FROM issue_replies r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN issue_replies parent ON parent.id = r.parent_reply_id
                LEFT JOIN users parent_user ON parent_user.id = parent.user_id
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
        $stmt = self::db()->prepare('SELECT id, file_path, mime_type, file_size, is_inline FROM issue_attachments WHERE issue_id = ? ORDER BY id');
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }

    public static function attachmentById(int $attachmentId): ?array
    {
        $stmt = self::db()->prepare('SELECT a.id, a.file_path, a.mime_type, a.file_size, a.issue_id FROM issue_attachments a JOIN issues i ON i.id = a.issue_id WHERE a.id = ? AND i.deleted_at IS NULL LIMIT 1');
        $stmt->execute([$attachmentId]);
        return $stmt->fetch() ?: null;
    }

    public static function participantIds(int $issueId): array
    {
        $stmt = self::db()->prepare(
            'SELECT user_id FROM issues WHERE id = ?
             UNION
             SELECT user_id FROM issue_replies WHERE issue_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$issueId, $issueId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function replyAuthor(int $replyId): ?int
    {
        $stmt = self::db()->prepare('SELECT user_id FROM issue_replies WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$replyId]);
        $userId = $stmt->fetchColumn();
        return $userId === false ? null : (int) $userId;
    }

    public static function diagnosticUpdates(int $issueId): array
    {
        $stmt = self::db()->prepare(
            'SELECT d.id, d.body, d.created_at, d.updated_at, u.id AS user_id, u.display_name
             FROM issue_diagnostic_updates d
             JOIN users u ON u.id = d.user_id
             WHERE d.issue_id = ? AND d.deleted_at IS NULL ORDER BY d.created_at ASC, d.id ASC'
        );
        $stmt->execute([$issueId]);
        return $stmt->fetchAll();
    }

    public static function editDiagnosticUpdate(int $issueId, int $updateId, int $userId, string $body): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT d.body FROM issue_diagnostic_updates d
                 JOIN issues i ON i.id = d.issue_id
                 WHERE d.id = ? AND d.issue_id = ? AND d.user_id = ? AND i.user_id = ?
                   AND i.status IN ('open','in_progress','awaiting_confirmation')
                   AND i.deleted_at IS NULL AND d.deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$updateId, $issueId, $userId, $userId]);
            $oldBody = $stmt->fetchColumn();
            if (!is_string($oldBody)) throw new \DomainException('diagnostic_update_not_editable');

            $pdo->prepare(
                'INSERT INTO issue_diagnostic_update_edit_history (diagnostic_update_id, editor_id, previous_body) VALUES (?, ?, ?)'
            )->execute([$updateId, $userId, $oldBody]);
            $pdo->prepare('UPDATE issue_diagnostic_updates SET body = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$body, $updateId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteDiagnosticUpdate(int $issueId, int $updateId, int $userId): void
    {
        $stmt = self::db()->prepare(
            "UPDATE issue_diagnostic_updates d
             JOIN issues i ON i.id = d.issue_id
             SET d.deleted_at = UTC_TIMESTAMP(), d.updated_at = UTC_TIMESTAMP()
             WHERE d.id = ? AND d.issue_id = ? AND d.user_id = ? AND i.user_id = ?
               AND i.status IN ('open','in_progress','awaiting_confirmation')
               AND i.deleted_at IS NULL AND d.deleted_at IS NULL"
        );
        $stmt->execute([$updateId, $issueId, $userId, $userId]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('diagnostic_update_not_deletable');
    }

    public static function addDiagnosticUpdate(int $issueId, int $userId, string $body): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT status FROM issues WHERE id = ? AND user_id = ?
                 AND status IN ('open','in_progress','awaiting_confirmation') AND deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$issueId, $userId]);
            $status = $stmt->fetchColumn();
            if ($status === false) throw new \DomainException('not_owner_or_closed');

            $pdo->prepare('INSERT INTO issue_diagnostic_updates (issue_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$issueId, $userId, $body]);
            $updateId = (int) $pdo->lastInsertId();
            if ($status === 'open') {
                $pdo->prepare("UPDATE issues SET status = 'in_progress', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                    ->execute([$issueId]);
            } else {
                $pdo->prepare('UPDATE issues SET updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$issueId]);
            }
            $pdo->commit();
            return $updateId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
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

    public static function addReply(int $issueId, int $userId, string $body, ?int $parentReplyId = null): int
    {
        $pdo = self::db();
        if ($parentReplyId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND parent_reply_id IS NULL AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$parentReplyId, $issueId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('invalid_parent_reply');
        }

        $stmt = $pdo->prepare(
            "INSERT INTO issue_replies (issue_id, parent_reply_id, user_id, body)
             SELECT ?, ?, ?, ? FROM issues WHERE id = ? AND status IN ('open','in_progress') AND deleted_at IS NULL"
        );
        $stmt->execute([$issueId, $parentReplyId, $userId, $body, $issueId]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('discussion_closed');
        return (int) $pdo->lastInsertId();
    }

    public static function replyIssueId(int $replyId): ?int
    {
        $stmt = self::db()->prepare('SELECT issue_id FROM issue_replies WHERE id = ? LIMIT 1');
        $stmt->execute([$replyId]);
        $issueId = $stmt->fetchColumn();
        return $issueId === false ? null : (int) $issueId;
    }

    public static function editReply(int $replyId, int $userId, string $body): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT r.body FROM issue_replies r
                 JOIN issues i ON i.id = r.issue_id
                 WHERE r.id = ? AND r.user_id = ? AND r.deleted_at IS NULL
                   AND i.status IN ('open','in_progress') AND i.deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$replyId, $userId]);
            $oldBody = $stmt->fetchColumn();
            if (!is_string($oldBody)) throw new \DomainException('reply_not_editable');

            $pdo->prepare('INSERT INTO issue_reply_edit_history (issue_reply_id, editor_id, previous_body) VALUES (?, ?, ?)')
                ->execute([$replyId, $userId, $oldBody]);
            $pdo->prepare('UPDATE issue_replies SET body = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$body, $replyId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function deleteReply(int $replyId, int $userId): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT r.issue_id, i.accepted_reply_id FROM issue_replies r
                 JOIN issues i ON i.id = r.issue_id
                 WHERE r.id = ? AND r.user_id = ? AND r.deleted_at IS NULL
                   AND i.status IN ('open','in_progress','awaiting_confirmation') AND i.deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$replyId, $userId]);
            $row = $stmt->fetch();
            if (!$row) throw new \DomainException('reply_not_deletable');

            $issueId = (int) $row['issue_id'];
            $pdo->prepare('UPDATE issue_replies SET parent_reply_id = NULL WHERE parent_reply_id = ? AND deleted_at IS NULL')
                ->execute([$replyId]);
            $pdo->prepare('UPDATE issue_replies SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$replyId]);
            if ((int) ($row['accepted_reply_id'] ?? 0) === $replyId) {
                $pdo->prepare(
                    "UPDATE issues SET accepted_reply_id = NULL, status = 'in_progress',
                        repair_confirmed_at = NULL, repair_confirmed_by = NULL, closed_at = NULL,
                        updated_at = UTC_TIMESTAMP() WHERE id = ?"
                )->execute([$issueId]);
            }
            $pdo->commit();
            return $issueId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function edit(int $issueId, int $userId, string $title, string $description, string $measurements): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT title, description, measurements FROM issues WHERE id = ? AND user_id = ? AND status IN ('open','in_progress') AND deleted_at IS NULL FOR UPDATE");
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
            $stmt = $pdo->prepare("SELECT id FROM issues WHERE id = ? AND user_id = ? AND content_type = 'help_request' AND status IN ('open','in_progress') AND deleted_at IS NULL FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('not_owner_or_closed');
            $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND deleted_at IS NULL');
            $stmt->execute([$replyId, $issueId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('reply_not_in_issue');
            $pdo->prepare("UPDATE issues SET accepted_reply_id = ?, status = 'awaiting_confirmation', updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$replyId, $issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function confirmRepair(int $issueId, int $ownerId, string $cause, string $action): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT accepted_reply_id FROM issues
                 WHERE id = ? AND user_id = ? AND content_type = 'help_request'
                   AND status = 'awaiting_confirmation' AND deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$issueId, $ownerId]);
            $acceptedReplyId = $stmt->fetchColumn();
            if ($acceptedReplyId === false || $acceptedReplyId === null) {
                throw new \DomainException('accepted_reply_required');
            }
            $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND deleted_at IS NULL');
            $stmt->execute([(int) $acceptedReplyId, $issueId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('accepted_reply_unavailable');

            $pdo->prepare(
                'INSERT INTO issue_results (issue_id, submitted_by, was_repaired, final_cause, repair_action, helpful_reply_id)
                 VALUES (?, ?, 1, ?, ?, ?)'
            )->execute([$issueId, $ownerId, $cause, $action, (int) $acceptedReplyId]);

            $pdo->prepare(
                "UPDATE issues SET status = 'resolved', repair_confirmed_at = UTC_TIMESTAMP(),
                    repair_confirmed_by = ?, closed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()
                 WHERE id = ?"
            )->execute([$ownerId, $issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function rejectRepairConfirmation(int $issueId, int $ownerId, string $reason): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM issues WHERE id = ? AND user_id = ? AND content_type = 'help_request'
                 AND status = 'awaiting_confirmation' AND deleted_at IS NULL FOR UPDATE"
            );
            $stmt->execute([$issueId, $ownerId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('not_awaiting_confirmation');

            if ($reason !== '') {
                $pdo->prepare('INSERT INTO issue_diagnostic_updates (issue_id, user_id, body) VALUES (?, ?, ?)')
                    ->execute([$issueId, $ownerId, $reason]);
            }
            $pdo->prepare(
                "UPDATE issues SET status = 'in_progress', accepted_reply_id = NULL,
                    repair_confirmed_at = NULL, repair_confirmed_by = NULL, closed_at = NULL,
                    updated_at = UTC_TIMESTAMP() WHERE id = ?"
            )->execute([$issueId]);
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
            $stmt = $pdo->prepare('SELECT id, submitted_by, was_repaired, review_status FROM issue_results WHERE id = ? AND issue_id = ? FOR UPDATE');
            $stmt->execute([$resultId, $issueId]);
            $result = $stmt->fetch();
            if (!$result || $result['review_status'] !== 'pending') {
                throw new \DomainException('result_not_pending');
            }
            $status = $approve ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE issue_results SET review_status = ?, reviewed_by = ?, reviewed_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$status, $reviewerId, $resultId]);
            if ($approve && (int) $result['was_repaired'] === 1) {
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
            $stmt = $pdo->prepare("SELECT status FROM issues WHERE id = ? AND user_id = ? AND content_type = 'help_request' AND status IN ('resolved','closed_unresolved') AND deleted_at IS NULL FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            $status = $stmt->fetchColumn();
            if ($status === false) {
                throw new \DomainException('not_reopenable');
            }
            $stmt = $pdo->prepare("SELECT review_status FROM issue_results WHERE issue_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$issueId]);
            $review = $stmt->fetchColumn();
            if ($review === false || $review === 'pending') {
                throw new \DomainException('result_pending_review');
            }
            $pdo->prepare("UPDATE issues SET status = 'open', accepted_reply_id = NULL,
                repair_confirmed_at = NULL, repair_confirmed_by = NULL, closed_at = NULL,
                updated_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function closeWithResult(int $issueId, int $ownerId, bool $repaired, string $cause, string $action, ?int $helpfulReplyId): void
    {
        if ($repaired) {
            throw new \DomainException('repair_requires_accepted_reply_and_confirmation');
        }
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id FROM issues WHERE id = ? AND user_id = ? AND content_type = 'help_request' AND status IN ('open','in_progress') AND deleted_at IS NULL FOR UPDATE");
            $stmt->execute([$issueId, $ownerId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('not_owner_or_closed');
            if ($helpfulReplyId !== null) {
                $stmt = $pdo->prepare('SELECT id FROM issue_replies WHERE id = ? AND issue_id = ? AND deleted_at IS NULL');
                $stmt->execute([$helpfulReplyId, $issueId]);
                if (!$stmt->fetchColumn()) throw new \DomainException('reply_not_in_issue');
            }
            $pdo->prepare(
                'INSERT INTO issue_results (issue_id, submitted_by, was_repaired, final_cause, repair_action, helpful_reply_id)
                 VALUES (?, ?, 0, ?, NULL, ?)'
            )->execute([$issueId, $ownerId, $cause, $helpfulReplyId]);
            $pdo->prepare("UPDATE issues SET status = 'closed_unresolved', closed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?")
                ->execute([$issueId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
