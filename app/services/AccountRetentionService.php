<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Completes the 30-day account-retention workflow through identity anonymization.
 *
 * This service never deletes rows. Published technical posts, replies,
 * diagnosis updates, repair results, attachments, schematics, edit history,
 * reputation records, personal-help content, and drafts remain in place.
 */
final class AccountRetentionService
{
    private const LOCK_NAME = 'zayan_hardware_account_retention';

    public static function run(bool $execute = false, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $pdo = Database::connection();
        self::assertRequiredSchema($pdo);
        $due = self::dueRequests($pdo, $limit);

        if (!$execute) {
            return [
                'mode' => 'dry-run',
                'due' => count($due),
                'processed' => 0,
                'skipped' => 0,
                'request_ids' => array_column($due, 'id'),
            ];
        }

        $lock = $pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)")->fetchColumn();
        if ((int) $lock !== 1) {
            throw new RuntimeException('Another account-retention worker is already running.');
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];
        try {
            foreach ($due as $item) {
                try {
                    if (self::anonymizeRequest($pdo, (int) $item['id'])) $processed++;
                    else $skipped++;
                } catch (Throwable $e) {
                    $errors[] = ['request_id' => (int) $item['id'], 'error' => $e->getMessage()];
                    App::log('ERROR', 'account.retention.failed', [
                        'request_id' => (int) $item['id'],
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
        }

        return [
            'mode' => 'execute',
            'due' => count($due),
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Fail closed before processing anything when the core anonymization schema
     * is incomplete. Optional feature tables are checked separately and skipped
     * only when that feature is not installed.
     */
    private static function assertRequiredSchema(PDO $pdo): void
    {
        $required = [
            'users' => [
                'id', 'username', 'display_name', 'email', 'phone', 'password_hash',
                'role', 'account_status', 'avatar_key', 'avatar_path',
                'accept_help_requests', 'last_login_at',
            ],
            'technician_profiles' => [
                'user_id', 'device_focus', 'tech_level', 'board_experience',
                'level_verified', 'workshop_name', 'biography',
            ],
            'account_deletion_requests' => ['id', 'user_id', 'status', 'scheduled_purge_at'],
            'terms_acceptances' => ['user_id', 'ip_hash'],
            'security_audit_logs' => ['actor_user_id', 'target_user_id', 'action', 'details_json', 'ip_hash'],
            'admin_password_resets' => ['user_id', 'admin_id', 'note'],
        ];

        $columnQuery = $pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $problems = [];
        foreach ($required as $table => $expectedColumns) {
            $columnQuery->execute([$table]);
            $columnRows = $columnQuery->fetchAll(PDO::FETCH_ASSOC);
            $columnTypes = [];
            foreach ($columnRows as $columnRow) {
                $columnTypes[(string) $columnRow['COLUMN_NAME']] = (string) $columnRow['COLUMN_TYPE'];
            }
            $actualColumns = array_keys($columnTypes);
            $missingColumns = array_values(array_diff($expectedColumns, $actualColumns));
            if ($missingColumns) {
                $problems[] = $table . ' missing columns: ' . implode(', ', $missingColumns);
            }
            if ($table === 'users' && isset($columnTypes['account_status'])) {
                foreach (["'active'", "'deletion_pending'", "'deleted'"] as $requiredValue) {
                    if (!str_contains($columnTypes['account_status'], $requiredValue)) {
                        $problems[] = 'users.account_status missing enum value ' . $requiredValue;
                    }
                }
            }
            if ($table === 'account_deletion_requests' && isset($columnTypes['status'])) {
                foreach (["'pending'", "'cancelled'", "'completed'"] as $requiredValue) {
                    if (!str_contains($columnTypes['status'], $requiredValue)) {
                        $problems[] = 'account_deletion_requests.status missing enum value ' . $requiredValue;
                    }
                }
            }
        }

        $optional = [
            'help_public_posts' => [
                'requester_user_id', 'helper_user_id', 'requester_display_name',
                'helper_display_name', 'requester_avatar_key', 'helper_avatar_key',
            ],
            'push_subscriptions' => [
                'id', 'user_id', 'endpoint', 'endpoint_hash', 'public_key',
                'auth_token', 'user_agent', 'last_used_at',
            ],
        ];
        $tableQuery = $pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $tablesToCheck = array_keys($required);
        foreach ($optional as $table => $expectedColumns) {
            $tableQuery->execute([$table]);
            $engine = $tableQuery->fetchColumn();
            if ($engine === false) continue;
            $tablesToCheck[] = $table;
            $columnQuery->execute([$table]);
            $columnRows = $columnQuery->fetchAll(PDO::FETCH_COLUMN, 0);
            $actualColumns = $columnRows;
            $missingColumns = array_values(array_diff($expectedColumns, $actualColumns));
            if ($missingColumns) {
                $problems[] = $table . ' missing columns: ' . implode(', ', $missingColumns);
            }
        }

        $engineQuery = $pdo->prepare(
            'SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        foreach (array_unique($tablesToCheck) as $table) {
            $engineQuery->execute([$table]);
            $engine = $engineQuery->fetchColumn();
            if (strcasecmp((string) $engine, 'InnoDB') !== 0) {
                $problems[] = $table . ' must use InnoDB for transactional anonymization';
            }
        }

        if ($problems) {
            throw new RuntimeException(
                'Account-retention schema is incomplete; no accounts were processed. ' . implode('; ', $problems)
            );
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    }

    private static function dueRequests(PDO $pdo, int $limit): array
    {
        $stmt = $pdo->query(
            "SELECT r.id
             FROM account_deletion_requests r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.status = 'pending'
               AND r.scheduled_purge_at IS NOT NULL
               AND r.scheduled_purge_at <= UTC_TIMESTAMP()
               AND u.account_status = 'deletion_pending'
             ORDER BY r.scheduled_purge_at ASC, r.id ASC
             LIMIT " . $limit
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function anonymizeRequest(PDO $pdo, int $requestId): bool
    {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "SELECT r.id, r.user_id, u.account_status
                 FROM account_deletion_requests r
                 INNER JOIN users u ON u.id = r.user_id
                 WHERE r.id = ? AND r.status = 'pending'
                   AND r.scheduled_purge_at IS NOT NULL
                   AND r.scheduled_purge_at <= UTC_TIMESTAMP()
                 FOR UPDATE"
            );
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request || $request['account_status'] !== 'deletion_pending') {
                $pdo->rollBack();
                return false;
            }

            $userId = (int) $request['user_id'];
            $anonymousUsername = self::uniqueAnonymousUsername($pdo, $userId);

            // Technical attachments are never touched by this worker.
            self::anonymizePublicHelpAuthor($pdo, $userId);
            self::anonymizeTechnicianProfile($pdo, $userId);
            self::disablePushSubscriptions($pdo, $userId);
            self::clearPersonalAuditMetadata($pdo, $userId);
            self::anonymizeUserRow($pdo, $userId, $anonymousUsername);

            $pdo->prepare(
                "UPDATE account_deletion_requests
                 SET status = 'completed',
                     completed_at = UTC_TIMESTAMP(),
                     note = 'Identity anonymized; all technical posts and technical files retained.'
                 WHERE user_id = ? AND status = 'pending'"
            )->execute([$userId]);

            $pdo->prepare(
                "INSERT INTO security_audit_logs
                    (actor_user_id, target_user_id, action, details_json, ip_hash)
                 VALUES (NULL, ?, 'account.deletion_completed', ?, NULL)"
            )->execute([
                $userId,
                json_encode([
                    'identity' => 'anonymized',
                    'technical_posts' => 'retained',
                    'replies_and_diagnosis' => 'retained',
                    'attachments_schematics_and_history' => 'retained',
                    'personal_help_content_and_drafts' => 'retained',
                    'account_identity_and_profile' => 'anonymized',
                    'private_avatar_cleanup' => 'post_commit_best_effort',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            $pdo->commit();

            // Filesystem changes cannot participate in the SQL transaction. Run
            // private-avatar cleanup only after the identity update is committed,
            // so a later SQL failure can never leave a still-active account
            // missing its avatar. AvatarController serves a generic avatar for
            // inactive accounts even if this best-effort cleanup must be retried.
            try {
                self::removePrivateAvatarFiles($userId);
            } catch (Throwable $cleanupError) {
                App::log('WARNING', 'account.retention.avatar_cleanup_failed', [
                    'user_id' => $userId,
                    'exception' => $cleanupError::class,
                    'message' => $cleanupError->getMessage(),
                ]);
            }

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function removePrivateAvatarFiles(int $userId): void
    {
        $privateRoot = App::root('storage/private');
        if (!file_exists($privateRoot) && !is_link($privateRoot)) {
            // Accounts without custom avatars may never have created private storage.
            return;
        }
        if (is_link($privateRoot) || !is_dir($privateRoot)) {
            throw new RuntimeException('Private storage root must be a real directory.');
        }
        $root = realpath($privateRoot);
        if ($root === false) throw new RuntimeException('Private storage directory is unavailable.');

        $avatarRootCandidate = $root . DIRECTORY_SEPARATOR . 'avatars';
        if (is_link($avatarRootCandidate)) {
            throw new RuntimeException('Avatar root must not be a symbolic link.');
        }
        if (!is_dir($avatarRootCandidate)) return;

        $avatarRoot = realpath($avatarRootCandidate);
        if ($avatarRoot === false || !str_starts_with($avatarRoot, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Avatar root is outside private storage.');
        }

        $candidate = $avatarRoot . DIRECTORY_SEPARATOR . $userId;
        if (is_link($candidate)) {
            throw new RuntimeException('Account avatar directory must not be a symbolic link.');
        }
        if (!is_dir($candidate)) return;

        $directory = realpath($candidate);
        if ($directory === false || $directory !== $candidate
            || !str_starts_with($directory, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Avatar directory is outside private storage.');
        }

        $entries = scandir($directory);
        if ($entries === false) throw new RuntimeException('Unable to inspect the avatar directory.');
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!preg_match('/^[a-f0-9]{40}\\.(?:jpg|png|webp)$/i', $entry)) continue;

            $candidateFile = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($candidateFile)) {
                if (!@unlink($candidateFile)) throw new RuntimeException('Unable to remove a private avatar symlink.');
                continue;
            }

            $file = realpath($candidateFile);
            if ($file === false) continue;
            if (!str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
                throw new RuntimeException('Avatar file resolved outside private storage.');
            }
            if (!@unlink($candidateFile)) throw new RuntimeException('Unable to remove a private avatar image.');
        }

        $remaining = scandir($directory);
        if ($remaining === false) throw new RuntimeException('Unable to verify avatar cleanup.');
        if (count($remaining) === 2) @rmdir($directory);
    }

    private static function anonymizePublicHelpAuthor(PDO $pdo, int $userId): void
    {
        if (!self::tableExists($pdo, 'help_public_posts')) return;

        $pdo->prepare(
            "UPDATE help_public_posts
             SET requester_display_name = 'عضو محذوف',
                 requester_avatar_key = 'technician-blue'
             WHERE requester_user_id = ?"
        )->execute([$userId]);

        $pdo->prepare(
            "UPDATE help_public_posts
             SET helper_display_name = 'عضو محذوف',
                 helper_avatar_key = 'technician-blue'
             WHERE helper_user_id = ?"
        )->execute([$userId]);
    }

    private static function anonymizeTechnicianProfile(PDO $pdo, int $userId): void
    {
        $columns = self::tableColumns($pdo, 'technician_profiles');
        if (!$columns) return;

        // Preserve technical credentials and expertise: device focus,
        // specialization, board experience, level verification, and skill
        // assessments are historical technical data, not account identity.
        $personalFields = [
            'phone' => '',
            'workshop_name' => '',
            'biography' => '',
        ];
        $sets = [];
        $params = [];
        foreach ($personalFields as $field => $fallback) {
            if (!isset($columns[$field])) continue;
            $params[] = $columns[$field]['is_nullable'] === 'YES' ? null : $fallback;
            $sets[] = $field . ' = ?';
        }
        if (!$sets) return;

        $params[] = $userId;
        $pdo->prepare('UPDATE technician_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?')
            ->execute($params);
    }

    private static function disablePushSubscriptions(PDO $pdo, int $userId): void
    {
        if (!self::tableExists($pdo, 'push_subscriptions')) return;

        $pdo->prepare(
            "UPDATE push_subscriptions
             SET endpoint = CONCAT('https://deleted.invalid/', user_id, '/', id),
                 endpoint_hash = SHA2(CONCAT('deleted-push:', id), 256),
                 public_key = SUBSTRING(SHA2(CONCAT('deleted-p256dh:', id), 256), 1, 43),
                 auth_token = SUBSTRING(SHA2(CONCAT('deleted-auth:', id), 256), 1, 22),
                 user_agent = NULL,
                 last_used_at = NULL
             WHERE user_id = ?"
        )->execute([$userId]);
    }

    private static function clearPersonalAuditMetadata(PDO $pdo, int $userId): void
    {
        $pdo->prepare('UPDATE terms_acceptances SET ip_hash = NULL WHERE user_id = ?')
            ->execute([$userId]);
        $pdo->prepare(
            'UPDATE security_audit_logs SET ip_hash = NULL
             WHERE actor_user_id = ? OR target_user_id = ?'
        )->execute([$userId, $userId]);
        $pdo->prepare(
            'UPDATE admin_password_resets SET note = NULL
             WHERE user_id = ? OR admin_id = ?'
        )->execute([$userId, $userId]);
    }

    private static function uniqueAnonymousUsername(PDO $pdo, int $userId): string
    {
        $check = $pdo->prepare('SELECT 1 FROM users WHERE username = ? AND id <> ? LIMIT 1');
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = 'deleted_' . bin2hex(random_bytes(6));
            $check->execute([$candidate, $userId]);
            if ($check->fetchColumn() === false) return $candidate;
        }
        throw new RuntimeException('Unable to reserve a unique anonymous username.');
    }

    private static function anonymizeUserRow(PDO $pdo, int $userId, string $username): void
    {
        $columns = self::tableColumns($pdo, 'users');
        $values = [
            'username' => $username,
            'display_name' => 'عضو محذوف',
            'email' => null,
            'phone' => null,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            'role' => 'member',
            'account_status' => 'deleted',
            'avatar_key' => 'technician-blue',
            'avatar_path' => null,
            'accept_help_requests' => 0,
            'last_login_at' => null,
        ];
        $sets = [];
        $params = [];
        foreach ($values as $field => $value) {
            if (!isset($columns[$field])) continue;
            $sets[] = $field . ' = ?';
            $params[] = $value;
        }
        if (isset($columns['updated_at'])) $sets[] = 'updated_at = UTC_TIMESTAMP()';
        if (!isset($columns['username']) || !isset($columns['account_status'])) {
            throw new RuntimeException('The users table is missing required retention columns.');
        }

        $params[] = $userId;
        $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')
            ->execute($params);
    }

    /** @return array<string,array{is_nullable:string,data_type:string}> */
    private static function tableColumns(PDO $pdo, string $table): array
    {
        if (!in_array($table, ['users', 'technician_profiles'], true)) {
            throw new RuntimeException('Invalid table metadata request.');
        }
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME, IS_NULLABLE, DATA_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[(string) $row['COLUMN_NAME']] = [
                'is_nullable' => (string) $row['IS_NULLABLE'],
                'data_type' => (string) $row['DATA_TYPE'],
            ];
        }
        return $columns;
    }
}
