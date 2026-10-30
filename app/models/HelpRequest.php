<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class HelpRequest extends Model
{
    public static function create(int $requesterId, int $helperId, string $subject, string $body): int
    {
        if ($requesterId < 1 || $helperId < 1 || $requesterId === $helperId) {
            throw new \DomainException('invalid_participants');
        }
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id, account_status, accept_help_requests FROM users WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$helperId]);
            $helper = $stmt->fetch();
            if (!$helper || $helper['account_status'] !== 'active' || (int) $helper['accept_help_requests'] !== 1) {
                throw new \DomainException('help_not_accepted');
            }
            $pdo->prepare(
                "INSERT INTO help_requests (requester_id, helper_id, subject, status) VALUES (?, ?, ?, 'pending')"
            )->execute([$requesterId, $helperId, $subject]);
            $requestId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO help_request_messages (request_id, sender_id, body) VALUES (?, ?, ?)'
            )->execute([$requestId, $requesterId, $body]);
            $pdo->prepare('UPDATE help_requests SET last_message_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$requestId]);
            $pdo->commit();
            return $requestId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function respond(int $requestId, int $helperId, bool $accept): void
    {
        $stmt = self::db()->prepare(
            "UPDATE help_requests SET status = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND helper_id = ? AND status = 'pending'"
        );
        $stmt->execute([$accept ? 'active' : 'declined', $requestId, $helperId]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('request_not_pending');
    }

    public static function sendMessage(int $requestId, int $senderId, string $body): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT requester_id, helper_id FROM help_requests
                 WHERE id = ? AND status = 'active' AND (requester_id = ? OR helper_id = ?) FOR UPDATE"
            );
            $stmt->execute([$requestId, $senderId, $senderId]);
            if (!$stmt->fetch()) throw new \DomainException('conversation_unavailable');
            $pdo->prepare('INSERT INTO help_request_messages (request_id, sender_id, body) VALUES (?, ?, ?)')
                ->execute([$requestId, $senderId, $body]);
            $messageId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE help_requests SET last_message_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([$requestId]);
            $pdo->commit();
            return $messageId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function close(int $requestId, int $userId): void
    {
        $stmt = self::db()->prepare(
            "UPDATE help_requests SET status = 'closed', closed_by = ?, closed_at = UTC_TIMESTAMP(),
                    updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'active' AND (requester_id = ? OR helper_id = ?)"
        );
        $stmt->execute([$userId, $requestId, $userId, $userId]);
        if ($stmt->rowCount() !== 1) throw new \DomainException('request_not_closable');
    }

    public static function forUser(int $userId, string $scope = 'all', int $limit = 100): array
    {
        $where = match ($scope) {
            'incoming' => 'h.helper_id = ?',
            'outgoing' => 'h.requester_id = ?',
            default => '(h.helper_id = ? OR h.requester_id = ?)',
        };
        $params = $scope === 'all' ? [$userId, $userId] : [$userId];
        $limit = max(1, min(100, $limit));
        $stmt = self::db()->prepare(
            'SELECT h.id, h.subject, h.status, h.created_at, h.updated_at, h.last_message_at,
                    h.requester_id, h.helper_id,
                    requester.display_name AS requester_name, requester.username AS requester_username,
                    requester.avatar_key AS requester_avatar_key, requester.avatar_path AS requester_avatar_path,
                    helper.display_name AS helper_name, helper.username AS helper_username,
                    helper.avatar_key AS helper_avatar_key, helper.avatar_path AS helper_avatar_path,
                    (SELECT m.body FROM help_request_messages m WHERE m.request_id = h.id ORDER BY m.id DESC LIMIT 1) AS last_message
             FROM help_requests h
             JOIN users requester ON requester.id = h.requester_id
             JOIN users helper ON helper.id = h.helper_id
             WHERE ' . $where . '
             ORDER BY h.last_message_at DESC, h.id DESC LIMIT ' . $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findForParticipant(int $requestId, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT h.*, requester.display_name AS requester_name, requester.username AS requester_username,
                    requester.avatar_key AS requester_avatar_key, requester.avatar_path AS requester_avatar_path,
                    helper.display_name AS helper_name, helper.username AS helper_username,
                    helper.avatar_key AS helper_avatar_key, helper.avatar_path AS helper_avatar_path
             FROM help_requests h
             JOIN users requester ON requester.id = h.requester_id
             JOIN users helper ON helper.id = h.helper_id
             WHERE h.id = ? AND (h.requester_id = ? OR h.helper_id = ?) LIMIT 1'
        );
        $stmt->execute([$requestId, $userId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function addAttachment(int $requestId, int $messageId, int $userId, string $path, string $mime, int $size): void
    {
        self::db()->prepare(
            'INSERT INTO help_request_attachments (request_id, message_id, uploaded_by, file_path, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$requestId, $messageId, $userId, $path, $mime, $size]);
    }

    public static function attachmentsForMessage(int $messageId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, mime_type, file_size FROM help_request_attachments WHERE message_id = ? ORDER BY id'
        );
        $stmt->execute([$messageId]);
        return $stmt->fetchAll();
    }

    public static function attachmentForParticipant(int $attachmentId, int $userId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT a.* FROM help_request_attachments a
             JOIN help_requests h ON h.id = a.request_id
             WHERE a.id = ? AND (h.requester_id = ? OR h.helper_id = ?) LIMIT 1'
        );
        $stmt->execute([$attachmentId, $userId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function messages(int $requestId): array
    {
        $stmt = self::db()->prepare(
            'SELECT m.id, m.sender_id, m.body, m.created_at, u.display_name AS sender_name,
                    u.avatar_key AS sender_avatar_key, u.avatar_path AS sender_avatar_path
             FROM help_request_messages m JOIN users u ON u.id = m.sender_id
             WHERE m.request_id = ? ORDER BY m.id ASC'
        );
        $stmt->execute([$requestId]);
        return $stmt->fetchAll();
    }

    public static function proposePublication(
        int $requestId, int $helperId, string $title, string $problem, string $diagnosis,
        string $solution, bool $helperShowIdentity
    ): int {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT id FROM help_requests WHERE id = ? AND helper_id = ? AND status = 'closed' FOR UPDATE"
            );
            $stmt->execute([$requestId, $helperId]);
            if (!$stmt->fetchColumn()) throw new \DomainException('publication_not_allowed');
            $pdo->prepare(
                'INSERT INTO help_request_publications
                    (request_id, proposed_by, helper_show_identity, draft_title, problem_summary, diagnosis_summary, solution_summary)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$requestId, $helperId, $helperShowIdentity ? 1 : 0, $title, $problem, $diagnosis, $solution]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new \DomainException('publication_already_proposed');
            }
            throw $e;
        }
    }

    public static function publicationForRequest(int $requestId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM help_request_publications WHERE request_id = ? LIMIT 1');
        $stmt->execute([$requestId]);
        return $stmt->fetch() ?: null;
    }

    public static function publicationForRequester(int $publicationId, int $requesterId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*, h.requester_id, h.helper_id
             FROM help_request_publications p
             JOIN help_requests h ON h.id = p.request_id
             WHERE p.id = ? AND h.requester_id = ? AND p.status = 'pending' LIMIT 1"
        );
        $stmt->execute([$publicationId, $requesterId]);
        return $stmt->fetch() ?: null;
    }

    public static function respondPublication(int $publicationId, int $requesterId, bool $consent, bool $showIdentity): ?int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT p.*, h.requester_id, h.helper_id
                 FROM help_request_publications p
                 JOIN help_requests h ON h.id = p.request_id
                 WHERE p.id = ? AND h.requester_id = ? AND p.status = 'pending' FOR UPDATE"
            );
            $stmt->execute([$publicationId, $requesterId]);
            $publication = $stmt->fetch();
            if (!$publication) throw new \DomainException('publication_not_pending');
            if (!$consent) {
                $pdo->prepare(
                    "UPDATE help_request_publications SET status = 'declined', requester_consented = 0,
                        requester_show_identity = 0, responded_at = UTC_TIMESTAMP() WHERE id = ?"
                )->execute([$publicationId]);
                $pdo->commit();
                return null;
            }
            $pdo->prepare(
                "UPDATE help_request_publications SET requester_consented = 1, requester_show_identity = ?,
                    status = 'published', responded_at = UTC_TIMESTAMP(), published_at = UTC_TIMESTAMP()
                 WHERE id = ?"
            )->execute([$showIdentity ? 1 : 0, $publicationId]);
            $requester = $showIdentity ? self::publicIdentity((int) $publication['requester_id']) : null;
            $helper = (int) $publication['helper_show_identity'] === 1
                ? self::publicIdentity((int) $publication['helper_id']) : null;
            $pdo->prepare(
                'INSERT INTO help_public_posts
                    (publication_id, request_id, title, problem_summary, diagnosis_summary, solution_summary,
                     requester_display_name, helper_display_name, requester_avatar_key, helper_avatar_key,
                     requester_user_id, helper_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $publicationId, (int) $publication['request_id'], $publication['draft_title'],
                $publication['problem_summary'], $publication['diagnosis_summary'], $publication['solution_summary'],
                $requester['display_name'] ?? null, $helper['display_name'] ?? null,
                $requester['avatar_key'] ?? null, $helper['avatar_key'] ?? null,
                $requester['id'] ?? null, $helper['id'] ?? null,
            ]);
            $postId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE help_request_publications SET public_post_id = ? WHERE id = ?')
                ->execute([$postId, $publicationId]);
            $pdo->commit();
            return $postId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function publicIdentity(int $userId): ?array
    {
        $stmt = self::db()->prepare('SELECT id, display_name, avatar_key FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function publicPosts(int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = self::db()->prepare(
            'SELECT id, title, problem_summary, diagnosis_summary, solution_summary,
                    requester_display_name, helper_display_name, requester_avatar_key, helper_avatar_key, requester_user_id, helper_user_id, created_at
             FROM help_public_posts ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function publicPost(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM help_public_posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function publicPostsByUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT p.id, p.title, p.problem_summary, p.diagnosis_summary, p.solution_summary,
                    p.requester_display_name, p.helper_display_name,
                    p.requester_avatar_key, p.helper_avatar_key, p.created_at
             FROM help_public_posts p
             JOIN help_request_publications pub ON pub.id = p.publication_id
             JOIN help_requests h ON h.id = p.request_id
             WHERE pub.status = \'published\'
               AND ((h.requester_id = ? AND pub.requester_show_identity = 1)
                 OR (h.helper_id = ? AND pub.helper_show_identity = 1))
             ORDER BY p.created_at DESC, p.id DESC LIMIT 50'
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = self::db()->prepare(
            "SELECT COUNT(*) FROM help_requests h
             WHERE h.status = 'pending' AND h.helper_id = ?"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}
