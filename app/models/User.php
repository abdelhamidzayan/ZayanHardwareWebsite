<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    /** True when an account identifier is already registered. */
    public static function exists(string $field, string $value): bool
    {
        if (!in_array($field, ['username', 'email', 'phone'], true)) {
            throw new \InvalidArgumentException('Invalid field.');
        }

        $stmt = self::db()->prepare('SELECT 1 FROM users WHERE ' . $field . ' = ? LIMIT 1');
        $stmt->execute([$value]);

        return $stmt->fetchColumn() !== false;
    }

    /** Returns usernames already taken from one bounded IN query. */
    public static function existingUsernames(array $usernames): array
    {
        $usernames = array_values(array_unique(array_filter($usernames, 'is_string')));
        if (!$usernames) return [];
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $stmt = self::db()->prepare('SELECT username FROM users WHERE username IN (' . $placeholders . ')');
        $stmt->execute($usernames);
        return array_column($stmt->fetchAll(), 'username');
    }

    /** Login lookup in exactly one identifier column, selected by the caller. */
    public static function findForLogin(string $identifier, string $type = 'username'): ?array
    {
        if (!in_array($type, ['username', 'email', 'phone'], true)) {
            throw new \InvalidArgumentException('Invalid login identifier type.');
        }

        $stmt = self::db()->prepare(
            'SELECT id, username, password_hash, account_status, auth_version
             FROM users
             WHERE ' . $type . ' = ?
             LIMIT 1'
        );
        $stmt->execute([$identifier]);
        return $stmt->fetch() ?: null;
    }

    /** Safe columns only: never returns the password hash. */
    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, username, display_name, email, role, account_status, created_at,
                   avatar_key, avatar_path, accept_help_requests
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** Returns the internal authentication version without exposing it to view data. */
    public static function authVersion(int $id): ?int
    {
        $stmt = self::db()->prepare('SELECT auth_version FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public static function findProfileByUsername(string $username): ?array
    {
        $sql = 'SELECT u.id, u.username, u.display_name, u.role, u.account_status, u.created_at,
                       u.avatar_key, u.avatar_path, u.accept_help_requests,
                       p.device_focus,
                       (SELECT GROUP_CONCAT(uds.device_type ORDER BY FIELD(uds.device_type, \'iphone\', \'android\') SEPARATOR \',\')
                        FROM user_device_specializations uds WHERE uds.user_id = u.id) AS device_specializations,
                       p.tech_level, p.board_experience, p.level_verified, p.workshop_name, p.biography
                FROM users u
                LEFT JOIN technician_profiles p ON p.user_id = u.id
                WHERE u.username = ? LIMIT 1';
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

    /**
     * Save registration-stage profile data using the normalized schema created
     * by the registration and skills migrations. Assessment-backed ratings are
     * historical evidence and are never deleted when self-selected skills change.
     */
    public static function saveRegistrationStage2(int $userId, string $username, string $specialization, ?string $experience, array $devices): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$username, $userId]);

            $validDevices = array_values(array_unique(array_filter(
                $devices,
                static fn ($device): bool => is_string($device) && in_array($device, ['iphone', 'android'], true)
            )));
            $deviceFocus = $validDevices[0] ?? null;
            $validExperience = in_array($experience, ['lt1', '1-3', '3-5', '5-10', 'gt10'], true)
                ? $experience : null;

            $pdo->prepare(
                'UPDATE technician_profiles SET device_focus = ?, board_experience = ? WHERE user_id = ?'
            )->execute([$deviceFocus, $validExperience, $userId]);

            $pdo->prepare('DELETE FROM user_device_specializations WHERE user_id = ?')->execute([$userId]);
            $deviceStmt = $pdo->prepare('INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, ?)');
            foreach ($validDevices as $device) {
                $deviceStmt->execute([$userId, $device]);
            }

            $pdo->prepare('DELETE FROM user_skill_domains WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare(
                'INSERT INTO user_skill_domains (user_id, skill_domain_id)
                 SELECT ?, id FROM skill_domains WHERE slug = ? AND is_active = 1'
            )->execute([$userId, $specialization]);
            $pdo->prepare(
                'INSERT IGNORE INTO user_skill_ratings (user_id, skill_domain_id, rating)
                 SELECT ?, id, 0 FROM skill_domains WHERE slug = ? AND is_active = 1'
            )->execute([$userId, $specialization]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Save the selected built-in avatar or a validated uploaded avatar path. */
    public static function saveAvatar(int $userId, string $avatarKey, ?string $avatarPath = null): void
    {
        $allowed = ['technician-blue', 'technician-cyan', 'technician-violet', 'technician-green', 'technician-orange', 'technician-red'];
        if ($avatarPath === null && !in_array($avatarKey, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid avatar key.');
        }
        self::db()->prepare('UPDATE users SET avatar_key = ?, avatar_path = ? WHERE id = ?')
            ->execute([$avatarKey, $avatarPath, $userId]);
    }

    public static function setHelpRequestsEnabled(int $userId, bool $enabled): void
    {
        self::db()->prepare('UPDATE users SET accept_help_requests = ? WHERE id = ?')
            ->execute([$enabled ? 1 : 0, $userId]);
    }

    /** Save optional profile details. */
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
            $pdo->prepare('UPDATE technician_profiles SET workshop_name = ? WHERE user_id = ?')
                ->execute([$workshop !== '' ? $workshop : null, $userId]);
            $pdo->prepare('DELETE FROM user_skill_domains WHERE user_id = ?')->execute([$userId]);
            // Ratings are backed by approved assessments. Editing the self-selected
            // skill list must never erase earned assessment history or reset scores.
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

    /**
     * Mark an account login without allowing a stale in-flight password check
     * to overwrite a password that an administrator has just reset.
     */
    public static function markLoggedIn(
        int $id,
        ?string $newHash = null,
        ?int $expectedAuthVersion = null
    ): bool {
        if ($expectedAuthVersion !== null) {
            if ($newHash !== null) {
                $stmt = self::db()->prepare(
                    "UPDATE users
                     SET last_login_at = UTC_TIMESTAMP(), password_hash = ?
                     WHERE id = ? AND account_status = 'active' AND auth_version = ?"
                );
                $stmt->execute([$newHash, $id, $expectedAuthVersion]);
            } else {
                $stmt = self::db()->prepare(
                    "UPDATE users SET last_login_at = UTC_TIMESTAMP()
                     WHERE id = ? AND account_status = 'active' AND auth_version = ?"
                );
                $stmt->execute([$id, $expectedAuthVersion]);
            }

            // rowCount() can be zero when last_login_at is unchanged within the
            // same SQL second. Read the version separately; no stale hash write
            // can occur because the UPDATE itself includes the version guard.
            return self::authVersion($id) === $expectedAuthVersion
                && self::isActive($id);
        }

        if ($newHash !== null) {
            self::db()->prepare(
                "UPDATE users SET last_login_at = UTC_TIMESTAMP(), password_hash = ?
                 WHERE id = ? AND account_status = 'active'"
            )->execute([$newHash, $id]);
        } else {
            self::db()->prepare(
                "UPDATE users SET last_login_at = UTC_TIMESTAMP()
                 WHERE id = ? AND account_status = 'active'"
            )->execute([$id]);
        }

        return self::isActive($id);
    }

    private static function isActive(int $id): bool
    {
        $stmt = self::db()->prepare(
            "SELECT 1 FROM users WHERE id = ? AND account_status = 'active' LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetchColumn() !== false;
    }

    /** Creates the user and technician profile in a single transaction. */
    public static function create(array $account, array $profile): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO users (username, display_name, email, phone, password_hash, role, account_status)
                 VALUES (?, ?, ?, ?, ?, \'member\', \'active\')'
            )->execute([
                $account['username'],
                $account['display_name'],
                $account['email'] ?? null,
                $account['phone'] ?? null,
                $account['password_hash'],
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO technician_profiles (user_id, device_focus, tech_level, board_experience, level_verified, biography, workshop_name)
                 VALUES (?, ?, ?, ?, 0, ?, ?)'
            )->execute([
                $id,
                $profile['device_focus'],
                $profile['tech_level'],
                $profile['board_experience'] ?? null,
                $profile['biography'] ?? null,
                $profile['workshop_name'] ?? null,
            ]);
            $deviceStmt = $pdo->prepare('INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, ?)');
            $devices = $profile['device_focuses'] ?? [$profile['device_focus']];
            foreach (array_unique($devices) as $device) {
                if (in_array($device, ['iphone', 'android'], true)) {
                    $deviceStmt->execute([$id, $device]);
                }
            }

            $pdo->prepare(
                'INSERT INTO terms_acceptances (user_id, terms_version, ip_hash) VALUES (?, ?, ?)'
            )->execute([
                $id,
                (string) ($account['terms_version'] ?? ''),
                $account['terms_ip_hash'] ?? null,
            ]);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
