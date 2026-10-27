<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    /** True when a user already has this username or email (field is allowlisted). */
    public static function exists(string $field, string $value): bool
    {
        if (!in_array($field, ['username', 'email'], true)) {
            throw new \InvalidArgumentException('Invalid field.');
        }

        $stmt = self::db()->prepare('SELECT 1 FROM users WHERE ' . $field . ' = ? LIMIT 1');
        $stmt->execute([$value]);

        return $stmt->fetchColumn() !== false;
    }

    /** Login lookup by email or username (already lowercased). */
    public static function findForLogin(string $identifier): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, password_hash, account_status FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);

        return $stmt->fetch() ?: null;
    }

    /** Safe columns only: never returns the password hash. */
    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, username, display_name, email, role, account_status, created_at FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findProfileByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT u.id, u.username, u.display_name, u.role, u.account_status, u.created_at,
                    p.specialization, p.experience, p.workshop_name, p.biography
             FROM users u
             LEFT JOIN technician_profiles p ON p.user_id = u.id
             WHERE u.username = ? LIMIT 1'
        );
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public static function markLoggedIn(int $id, ?string $newHash = null): void
    {
        if ($newHash !== null) {
            self::db()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(), password_hash = ? WHERE id = ?')
                ->execute([$newHash, $id]);
            return;
        }

        self::db()->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
    }

    /**
     * Creates the account and its technician profile in one transaction.
     * @return int new user id
     */
    public static function create(array $account, array $profile): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'INSERT INTO users (username, display_name, email, password_hash) VALUES (?, ?, ?, ?)'
            )->execute([
                $account['username'],
                $account['display_name'],
                $account['email'],
                $account['password_hash'],
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO technician_profiles (user_id, specialization, experience, workshop_name, phone)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $id,
                $profile['specialization'],
                $profile['experience'],
                $profile['workshop_name'],
                $profile['phone'],
            ]);

            $pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
