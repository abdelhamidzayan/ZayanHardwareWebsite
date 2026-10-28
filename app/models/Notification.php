<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Notification extends Model
{
    public static function create(
        int $userId,
        ?int $actorUserId,
        ?int $issueId,
        ?int $replyId,
        string $type,
        string $title,
        string $body,
        string $targetPath,
        string $eventKey
    ): bool {
        $stmt = self::db()->prepare(
            'INSERT IGNORE INTO notifications
                (user_id, actor_user_id, issue_id, reply_id, type, title, body, target_path, event_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $actorUserId, $issueId, $replyId, $type, $title, $body, $targetPath, $eventKey]);
        return $stmt->rowCount() === 1;
    }

    public static function forUser(int $userId, int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = self::db()->prepare(
            'SELECT n.id, n.actor_user_id, n.issue_id, n.reply_id, n.type, n.title, n.body,
                    n.target_path, n.is_read, n.created_at, n.read_at,
                    u.display_name AS actor_name
             FROM notifications n
             LEFT JOIN users u ON u.id = n.actor_user_id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function unreadCount(int $userId): int
    {
        $stmt = self::db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function markRead(int $notificationId, int $userId): ?string
    {
        $pdo = self::db();
        $stmt = $pdo->prepare(
            'SELECT target_path FROM notifications WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$notificationId, $userId]);
        $path = $stmt->fetchColumn();
        if (!is_string($path)) return null;

        $pdo->prepare(
            'UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, UTC_TIMESTAMP())
             WHERE id = ? AND user_id = ?'
        )->execute([$notificationId, $userId]);
        return $path;
    }

    public static function markAllRead(int $userId): void
    {
        self::db()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = COALESCE(read_at, UTC_TIMESTAMP())
             WHERE user_id = ? AND is_read = 0'
        )->execute([$userId]);
    }
}
