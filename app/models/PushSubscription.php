<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PushSubscription extends Model
{
    public static function save(int $userId, string $endpoint, string $publicKey, string $authToken, string $userAgent): void
    {
        $endpointHash = hash('sha256', $endpoint);
        $stmt = self::db()->prepare(
            'INSERT INTO push_subscriptions
                (user_id, endpoint, endpoint_hash, public_key, auth_token, content_encoding, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), endpoint = VALUES(endpoint),
                public_key = VALUES(public_key), auth_token = VALUES(auth_token),
                content_encoding = VALUES(content_encoding), user_agent = VALUES(user_agent), updated_at = UTC_TIMESTAMP()'
        );
        $stmt->execute([$userId, $endpoint, $endpointHash, $publicKey, $authToken, 'aes128gcm', mb_substr($userAgent, 0, 255)]);
    }

    public static function forUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT id, endpoint, public_key, auth_token, content_encoding
             FROM push_subscriptions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function deleteForUserByEndpoint(int $userId, string $endpoint): void
    {
        self::db()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?')
            ->execute([$userId, hash('sha256', $endpoint)]);
    }

    public static function deleteById(int $id): void
    {
        self::db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$id]);
    }
}
