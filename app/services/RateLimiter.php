<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Database;

/** Persistent rate limiting on top of the rate_limit_hits table (works across requests). */
final class RateLimiter
{
    public static function tooMany(string $bucket, int $max, int $windowSeconds): bool
    {
        $sql = 'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = ? AND hit_at > (UTC_TIMESTAMP() - INTERVAL '
            . (int) $windowSeconds . ' SECOND)';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$bucket]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    public static function hit(string $bucket): void
    {
        $pdo = Database::connection();
        $pdo->prepare('INSERT INTO rate_limit_hits (bucket, hit_at) VALUES (?, UTC_TIMESTAMP())')->execute([$bucket]);

        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM rate_limit_hits WHERE hit_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)');
        }
    }
}
