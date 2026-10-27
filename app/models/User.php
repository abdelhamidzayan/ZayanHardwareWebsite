<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    /** True when an account identifier is already registered. */
    public static function exists(string $field, string $value): bool
    {
        if (!in_array($field, ['username', 'email'], true)) {
            throw new \InvalidArgumentException('Invalid field.');
        }

        $stmt = self::db()->prepare('SELECT 1 FROM users WHERE ' . $field . ' = ? LIMIT 1');
        $stmt->execute([$value]);

        return $stmt->fetchColumn() !== false;
    }

    public static function phoneExists(string $phone): bool
    {
        $stmt = self::db()->prepare('SELECT 1 FROM technician_profiles WHERE phone = ? LIMIT 1');
        $stmt->execute([$phone]);

        return $stmt->fetchColumn() !== false;
    }

    /** Login lookup by email or phone only; usernames are not login identifiers. */
    public static function findForLogin(string $identifier): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT u.id, u.password_hash, u.account_status
             FROM users u
             LEFT JOIN technician_profiles p ON p.user_id = u.id
             WHERE u.email = ? OR p.phone = ?
             LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);

        return $stmt->fetch() ?: null;
    }

    /** Safe columns only: never returns the password hash. */
    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, username, display_name, email, role, account_status, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public static function findProfileByUsername(string $username): ?array
    {
        $sql = "SELECT u.id, u.username, u.display_name, u.role, u.account_status, u.created_at,
                       p.specialization, p.experience, p.workshop_name, p.biography,
                       (SELECT GROUP_CONCAT(ds.device_type ORDER BY ds.device_type SEPARATOR ',')
                        FROM user_device_specializations ds WHERE ds.user_id = u.id) AS device_specializations,
                       (SELECT GROUP_CONCAT(sd.name_ar ORDER BY sd.sort_order SEPARATOR '، ')
                        FROM user_skill_domains usd JOIN skill_domains sd ON sd.id = usd.skill_domain_id
                        WHERE usd.user_id = u.id) AS skill_domains
                FROM users u
                LEFT JOIN technician_profiles p ON p.user_id = u.id
                WHERE u.username = ? LIMIT 1";
        $stmt = self::db()->prepare($sql);
        $stmt->execute([$username]);

        return $stmt->fetch() ?: null;
    }

    public static function skillRatings(int $userId): array
    {
        $stmt = self::db()->prepare(
            'SELECT d.name_ar AS skill_name, r.rating, r.approved_assessments
             FROM user_skill_ratings r
             JOIN skill_domains d ON d.id = r.skill_domain_id
             WHERE r.user_id = ?
             ORDER BY d.sort_order, d.name_ar'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Save the account's username and core technician specialization from registration stage 2. */
    public static function saveRegistrationStage2(int $userId, string $username, string $specialization, ?string $experience, array $devices): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$username, $userId]);
            $pdo->prepare('UPDATE technician_profiles SET specialization = ?, experience = ? WHERE user_id = ?')
                ->execute([$specialization, $experience, $userId]);
            $pdo->prepare('DELETE FROM user_device_specializations WHERE user_id = ?')->execute([$userId]);
            $deviceStmt = $pdo->prepare('INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, ?)');
            foreach (array_unique($devices) as $device) {
                if (in_array($device, ['iphone', 'android'], true)) {
                    $deviceStmt->execute([$userId, $device]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Save optional profile details; avatar upload is intentionally not implemented yet. */
    public static function saveOptionalProfile(int $userId, string $workshop, string $biography): void
    {
        self::db()->prepare('UPDATE technician_profiles SET workshop_name = ?, biography = ? WHERE user_id = ?')
            ->execute([$workshop !== '' ? $workshop : null, $biography !== '' ? $biography : null, $userId]);
    }

    /** Replace a user's optional device specializations. */
    public static function saveDeviceSpecializations(int $userId, array $devices): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM user_device_specializations WHERE user_id = ?')->execute([$userId]);
            $stmt = $pdo->prepare('INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, ?)');
            foreach (array_unique($devices) as $device) {
                if (in_array($device, ['iphone', 'android'], true)) {
                    $stmt->execute([$userId, $device]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Save optional workshop and technical skill domains. */
    public static function saveOptionalSkills(int $userId, array $skills, string $workshop): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE technician_profiles SET workshop_name = ?, specialization = ? WHERE user_id = ?')
                ->execute([$workshop !== '' ? $workshop : null, $skills[0] ?? null, $userId]);
            $pdo->prepare('DELETE FROM user_skill_domains WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare('DELETE FROM user_skill_ratings WHERE user_id = ?')->execute([$userId]);
            $domainStmt = $pdo->prepare('INSERT INTO user_skill_domains (user_id, skill_domain_id) SELECT ?, id FROM skill_domains WHERE slug = ? AND is_active = 1');
            $ratingStmt = $pdo->prepare('INSERT IGNORE INTO user_skill_ratings (user_id, skill_domain_id, rating) SELECT ?, id, 0 FROM skill_domains WHERE slug = ? AND is_active = 1');
            foreach (array_unique($skills) as $skill) {
                $domainStmt->execute([$userId, $skill]);
                $ratingStmt->execute([$userId, $skill]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
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
     * Creates account, optional/incomplete profile, device coverage, skill domains and terms acceptance atomically.
     * @return int new user id
     */
    public static function create(array $account, array $profile): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'INSERT INTO users (username, display_name, email, password_hash, role, account_status)
                 VALUES (?, ?, ?, ?, \'member\', \'active\')'
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
                $profile['specialization'] ?? null,
                $profile['experience'] ?? null,
                $profile['workshop_name'] ?? null,
                $profile['phone'] ?? null,
            ]);

            $deviceStmt = $pdo->prepare('INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, ?)');
            foreach (($profile['device_specializations'] ?? []) as $device) {
                $deviceStmt->execute([$id, $device]);
            }

            $skillStmt = $pdo->prepare(
                'INSERT INTO user_skill_domains (user_id, skill_domain_id)
                 SELECT ?, id FROM skill_domains WHERE slug = ? AND is_active = 1'
            );
            foreach (($profile['skill_domains'] ?? []) as $skill) {
                $skillStmt->execute([$id, $skill]);
                $ratingStmt = $pdo->prepare(
                    'INSERT IGNORE INTO user_skill_ratings (user_id, skill_domain_id, rating)
                     SELECT ?, id, 0 FROM skill_domains WHERE slug = ?'
                );
                $ratingStmt->execute([$id, $skill]);
            }

            $pdo->prepare(
                'INSERT INTO terms_acceptances (user_id, terms_version, ip_hash) VALUES (?, ?, ?)'
            )->execute([
                $id,
                $profile['terms_version'] ?? '1.0',
                $profile['ip_hash'] ?? null,
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
