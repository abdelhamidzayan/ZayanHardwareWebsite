<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\App;

final class Database
{
    private static ?\PDO $pdo = null;

    /** Connects on first use, so pages without database access work without MySQL. */
    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::newConnection();
        }

        return self::$pdo;
    }

    /**
     * Create a separate connection for durable recovery/audit writes.
     * It must not share a failed or uncommitted request transaction.
     */
    public static function newConnection(): \PDO
    {
        $c = (array) App::config('database', []);
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);

        $pdo = new \PDO($dsn, $c['user'], $c['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

}
