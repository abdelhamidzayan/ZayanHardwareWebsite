<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Database;

final class RateLimiter
{
    /** Atomically limits an action per account and per source IP. */
    public static function allowForUser(
        string $action,
        int $userId,
        int $max,
        int $windowSeconds,
        ?int $ipMax = null
    ): bool {
        if ($userId < 1) return false;
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,47}$/', $action)) {
            throw new \InvalidArgumentException('Invalid rate-limit action.');
        }

        $max = max(1, min(1000, $max));
        $ipMax = max(1, min(10000, $ipMax ?? ($max * 5)));
        $windowSeconds = max(1, min(86400, $windowSeconds));
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = is_string($ip) ? $ip : 'unknown';
        $buckets = [
            ['key' => $action . ':user:' . $userId, 'limit' => $max],
            ['key' => $action . ':ip:' . hash('sha256', $ip), 'limit' => $ipMax],
        ];
        usort($buckets, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO rate_limit_buckets (bucket, window_started_at, hit_count)
                 VALUES (?, UTC_TIMESTAMP(), 0)'
            );
            foreach ($buckets as $bucket) $insert->execute([$bucket['key']]);

            $select = $pdo->prepare(
                'SELECT hit_count,
                        (window_started_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $windowSeconds . ' SECOND)) AS expired
                 FROM rate_limit_buckets WHERE bucket = ? FOR UPDATE'
            );
            $states = [];
            foreach ($buckets as $bucket) {
                $select->execute([$bucket['key']]);
                $row = $select->fetch();
                if (!$row) throw new \RuntimeException('Unable to lock rate-limit bucket.');
                $states[] = [
                    'key' => $bucket['key'],
                    'limit' => $bucket['limit'],
                    'count' => (int) $row['hit_count'],
                    'expired' => (int) $row['expired'] === 1,
                ];
            }

            foreach ($states as $state) {
                if (!$state['expired'] && $state['count'] >= $state['limit']) {
                    $pdo->commit();
                    return false;
                }
            }

            $reset = $pdo->prepare(
                'UPDATE rate_limit_buckets
                 SET window_started_at = UTC_TIMESTAMP(), hit_count = 1
                 WHERE bucket = ?'
            );
            $increment = $pdo->prepare(
                'UPDATE rate_limit_buckets SET hit_count = hit_count + 1 WHERE bucket = ?'
            );
            foreach ($states as $state) {
                if ($state['expired']) $reset->execute([$state['key']]);
                else $increment->execute([$state['key']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        if (mt_rand(1, 100) === 1) {
            try {
                $pdo->exec(
                    'DELETE FROM rate_limit_buckets
                     WHERE updated_at < (UTC_TIMESTAMP() - INTERVAL 2 DAY)'
                );
            } catch (\\Throwable $cleanupError) {
                \\App\\Core\\App::log('warning', 'Rate-limit bucket cleanup failed: ' . $cleanupError->getMessage());
            }
        }
        return true;
    }
}
