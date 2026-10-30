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
            $anonymousUsername = 'deleted' . base_convert((string) $userId, 10, 36);

            // Remove account avatar binaries only; technical attachments are untouched.
            self::removePrivateAvatarFiles($userId);
            self::anonymizePublicHelpAuthor($pdo, $userId);
            self::anonymizeTechnicianProfile($pdo, $userId);
            self::disablePushSubscriptions($pdo, $userId);
            self::clearPersonalAuditMetadata($pdo, $userId);
            self::anonymizeUserRow($pdo, $userId, $anonymousUsername);

            $pdo->prepare(
                "UPDATE account_deletion_requests
                 SET status = 'completed',
                     completed_at = UTC_TIMESTAMP(),
                     note = 'Identity anonymized; all technical content and related files retained.'
                 WHERE user_id = ? AND status = 'pending'
                   AND scheduled_purge_at IS NOT NULL
                   AND scheduled_purge_at <= UTC_TIMESTAMP()"
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
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function removePrivateAvatarFiles(int $userId): void
    {
        $root = realpath(App::root('storage/private'));
        if ($root === false) throw new RuntimeException('Private storage directory is unavailable.');

        $relative = 'avatars/' . $userId;
        $candidate = $root . DIRECTORY_SEPARATOR . $relative;
        if (!is_dir($candidate)) return;

        $directory = realpath($candidate);
        if ($directory === false || !str_starts_with($directory, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Avatar directory is outside private storage.');
        }

        $entries = scandir($directory);
        if ($entries === false) throw new RuntimeException('Unable to inspect the avatar directory.');
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (!preg_match('/^[a-f0-9]{40}\\.(?:jpg|png|webp)$/i', $entry)) continue;

            $file = realpath($directory . DIRECTORY_SEPARATOR . $entry);
            if ($file === false) continue;
            if (!str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
                throw new RuntimeException('Avatar file resolved outside private storage.');
            }
            if (!@unlink($file)) throw new RuntimeException('Unable to remove a private avatar image.');
        }

        $remaining = scandir($directory);
        if ($remaining === false) throw new RuntimeException('Unable to verify avatar cleanup.');
        if (count($remaining) === 2) @rmdir($directory);
    }

    private static function anonymizePublicHelpAuthor(PDO $pdo, int $userId): void
    {
        $pdo->prepare(
            "UPDATE help_public_posts
             SET requester_display_name = 'عضو محذوف',
                 requester_avatar_key = 'technician-blue',
                 requester_user_id = NULL
             WHERE requester_user_id = ?"
        )->execute([$userId]);

        $pdo->prepare(
            "UPDATE help_public_posts
             SET helper_display_name = 'عضو محذوف',
                 helper_avatar_key = 'technician-blue',
                 helper_user_id = NULL
             WHERE helper_user_id = ?"
        )->execute([$userId]);
    }

    private static function anonymizeTechnicianProfile(PDO $pdo, int $userId): void
    {
        $columns = self::tableColumns($pdo, 'technician_profiles');
        if (!$columns) return;

        $fields = [
            'phone' => null,
            'device_focus' => 'android',
            'board_experience' => 'lt1',
            'specialization' => '',
            'experience' => 'lt1',
            'workshop_name' => '',
            'biography' => '',
        ];
        $sets = [];
        $params = [];
        foreach ($fields as $field => $fallback) {
            if (!isset($columns[$field])) continue;
            $params[] = $columns[$field]['is_nullable'] === 'YES' ? null : $fallback;
            $sets[] = $field . ' = ?';
        }
        if (isset($columns['tech_level'])) $sets[] = "tech_level = 'fast'";
        if (isset($columns['level_verified'])) $sets[] = 'level_verified = 0';
        if (!$sets) return;

        $params[] = $userId;
        $pdo->prepare('UPDATE technician_profiles SET ' . implode(', ', $sets) . ' WHERE user_id = ?')
            ->execute($params);
    }

    private static function disablePushSubscriptions(PDO $pdo, int $userId): void
    {
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
