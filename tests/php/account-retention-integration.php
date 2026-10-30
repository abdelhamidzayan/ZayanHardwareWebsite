<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Services\AccountRetentionService;
use App\Services\ContentEditorService;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'App\Core\\' => '/app/core/',
        'App\Controllers\\' => '/app/controllers/',
        'App\Models\\' => '/app/models/',
        'App\Services\\' => '/app/services/',
        'App\Middleware\\' => '/app/middleware/',
    ];
    foreach ($map as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $file = $root . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    }
});

function retentionExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function ensureTestColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    if ($stmt->fetchColumn() === false) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function retentionCount(PDO $pdo, string $table): int
{
    $allowed = [
        'issues', 'issue_attachments', 'issue_replies', 'issue_diagnostic_updates',
        'issue_results', 'issue_edit_history', 'issue_reply_edit_history',
        'issue_diagnostic_update_edit_history', 'reply_votes', 'reputation_events',
        'help_requests', 'help_request_messages', 'help_request_attachments',
        'help_request_publications', 'help_public_posts', 'post_drafts', 'post_draft_media',
        'user_device_specializations', 'user_skill_domains', 'user_skill_ratings', 'skill_tests', 'skill_test_attempts',
    ];
    if (!in_array($table, $allowed, true)) throw new InvalidArgumentException('Invalid test table.');
    return (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
}

function createPublicHelpPost(PDO $pdo, int $requesterId, int $helperId, string $label): array
{
    $pdo->prepare(
        'INSERT INTO help_requests (requester_id, helper_id, subject) VALUES (?, ?, ?)'
    )->execute([$requesterId, $helperId, $label]);
    $requestId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO help_request_publications
            (request_id, proposed_by, status, requester_consented, requester_show_identity,
             helper_show_identity, draft_title, problem_summary, diagnosis_summary, solution_summary)
         VALUES (?, ?, 'published', 1, 0, 0, ?, 'Problem summary', 'Diagnosis summary', 'Solution summary')"
    )->execute([$requestId, $helperId, $label]);
    $publicationId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO help_public_posts
            (publication_id, request_id, title, problem_summary, diagnosis_summary, solution_summary,
             requester_display_name, helper_display_name, requester_avatar_key, helper_avatar_key,
             requester_user_id, helper_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $publicationId, $requestId, $label, 'Problem summary', 'Diagnosis summary', 'Solution summary',
        'Original requester', 'Original helper', 'technician-blue', 'technician-blue',
        $requesterId, $helperId,
    ]);

    return ['request_id' => $requestId, 'publication_id' => $publicationId, 'post_id' => (int) $pdo->lastInsertId()];
}

App::boot($root);
$pdo = Database::connection();

foreach ([
    'username' => "VARCHAR(48) NOT NULL DEFAULT ''",
    'display_name' => "VARCHAR(80) NOT NULL DEFAULT ''",
    'email' => 'VARCHAR(190) NULL',
    'phone' => 'VARCHAR(32) NULL',
    'password_hash' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'role' => "VARCHAR(24) NOT NULL DEFAULT 'member'",
    'account_status' => "VARCHAR(24) NOT NULL DEFAULT 'active'",
    'last_login_at' => 'DATETIME NULL',
    'updated_at' => 'DATETIME NULL',
] as $column => $definition) {
    ensureTestColumn($pdo, 'users', $column, $definition);
}

$fixtures = [
    "CREATE TABLE IF NOT EXISTS account_deletion_requests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        status ENUM('pending','cancelled','completed') NOT NULL DEFAULT 'pending',
        scheduled_purge_at DATETIME NULL,
        completed_at DATETIME NULL,
        note VARCHAR(255) NULL,
        PRIMARY KEY (id),
        KEY idx_test_deletion_due (status, scheduled_purge_at),
        CONSTRAINT fk_test_retention_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS security_audit_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_user_id BIGINT UNSIGNED NULL,
        target_user_id BIGINT UNSIGNED NULL,
        action VARCHAR(80) NOT NULL,
        details_json LONGTEXT NULL,
        ip_hash CHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_test_audit_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS admin_password_resets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        admin_id BIGINT UNSIGNED NOT NULL,
        note VARCHAR(255) NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_reset_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        endpoint VARCHAR(2048) NOT NULL,
        endpoint_hash CHAR(64) NOT NULL,
        public_key VARCHAR(128) NOT NULL,
        auth_token VARCHAR(128) NOT NULL,
        user_agent VARCHAR(255) NULL,
        last_used_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_test_push_user (user_id),
        CONSTRAINT fk_test_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS technician_profiles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        device_focus ENUM('iphone','android') NULL,
        tech_level ENUM('fast','board') NOT NULL DEFAULT 'fast',
        board_experience ENUM('lt1','1-3','3-5','5-10','gt10') NULL,
        level_verified TINYINT(1) NOT NULL DEFAULT 0,
        workshop_name VARCHAR(120) NULL,
        biography TEXT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_test_profile_user (user_id),
        CONSTRAINT fk_test_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_replies (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        issue_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        body TEXT NOT NULL,
        deleted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_reply_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_reply_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_diagnostic_updates (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        issue_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        body TEXT NOT NULL,
        deleted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_diagnostic_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_diagnostic_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_results (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        issue_id BIGINT UNSIGNED NOT NULL,
        submitted_by BIGINT UNSIGNED NOT NULL,
        was_repaired TINYINT(1) NOT NULL DEFAULT 0,
        final_cause TEXT NOT NULL,
        repair_action TEXT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_result_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_result_user FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_edit_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        issue_id BIGINT UNSIGNED NOT NULL,
        editor_id BIGINT UNSIGNED NOT NULL,
        previous_title VARCHAR(180) NOT NULL,
        previous_description TEXT NOT NULL,
        previous_measurements TEXT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_issue_history_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_issue_history_user FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_reply_edit_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        issue_reply_id BIGINT UNSIGNED NOT NULL,
        editor_id BIGINT UNSIGNED NOT NULL,
        previous_body TEXT NOT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_reply_history_reply FOREIGN KEY (issue_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_reply_history_user FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS issue_diagnostic_update_edit_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        diagnostic_update_id BIGINT UNSIGNED NOT NULL,
        editor_id BIGINT UNSIGNED NOT NULL,
        previous_body TEXT NOT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_diag_history_update FOREIGN KEY (diagnostic_update_id) REFERENCES issue_diagnostic_updates(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_diag_history_user FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS reply_votes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        reply_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        vote_type VARCHAR(20) NOT NULL DEFAULT 'helpful',
        PRIMARY KEY (id),
        CONSTRAINT fk_test_vote_reply FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
        CONSTRAINT fk_test_vote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS reputation_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        event_key VARCHAR(190) NOT NULL,
        points INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        CONSTRAINT fk_test_reputation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($fixtures as $sql) $pdo->exec($sql);

$pdo->beginTransaction();
try {
    $userInsert = $pdo->prepare(
        "INSERT INTO users
            (username, display_name, email, phone, password_hash, role, account_status,
             avatar_key, avatar_path, accept_help_requests, last_login_at)
         VALUES (?, ?, ?, ?, ?, 'member', 'active', 'technician-orange', NULL, 1, UTC_TIMESTAMP())"
    );
    $userInsert->execute(['retention_owner_' . bin2hex(random_bytes(3)), 'Original Owner', 'owner@example.test', '+201000000001', password_hash('temporary-password', PASSWORD_DEFAULT)]);
    $ownerId = (int) $pdo->lastInsertId();
    $userInsert->execute(['retention_helper_' . bin2hex(random_bytes(3)), 'Original Helper', 'helper@example.test', '+201000000002', password_hash('helper-password', PASSWORD_DEFAULT)]);
    $helperId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO users
            (username, display_name, email, phone, password_hash, role, account_status,
             avatar_key, avatar_path, accept_help_requests, last_login_at)
         VALUES (?, ?, ?, ?, ?, 'member', 'active', 'technician-violet', NULL, 1, UTC_TIMESTAMP())"
    )->execute(['retention_rollback_' . bin2hex(random_bytes(3)), 'Rollback Owner', 'rollback@example.test', '+201000000003', password_hash('rollback-password', PASSWORD_DEFAULT)]);
    $rollbackUserId = (int) $pdo->lastInsertId();

    $pdo->prepare(
    "INSERT INTO technician_profiles
        (user_id, device_focus, tech_level, board_experience, level_verified, workshop_name, biography)
     VALUES (?, 'android', 'board', '1-3', 1, 'Rollback Workshop', 'Keep this profile if transaction fails')"
)->execute([$rollbackUserId]);
$pdo->prepare(
    "INSERT INTO account_deletion_requests (user_id, status, scheduled_purge_at)
     VALUES (?, 'pending', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE))"
)->execute([$rollbackUserId]);
$rollbackRequestId = (int) $pdo->lastInsertId();
$pdo->prepare("UPDATE users SET account_status = 'deletion_pending' WHERE id = ?")->execute([$rollbackUserId]);

$pdo->exec(
    "CREATE TRIGGER trg_test_retention_rollback
     BEFORE INSERT ON security_audit_logs
     FOR EACH ROW
     BEGIN
       IF NEW.action = 'account.deletion_completed' AND NEW.target_user_id = $rollbackUserId THEN
         SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced account retention rollback test';
       END IF;
     END"
);
try {
    $failedRun = AccountRetentionService::run(true, 100);
    retentionExpect(count($failedRun['errors'] ?? []) === 1 && $failedRun['processed'] === 0, 'Injected audit failure must be reported.');
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_test_retention_rollback');
}

$rollbackStmt = $pdo->prepare('SELECT account_status, email FROM users WHERE id = ?');
$rollbackStmt->execute([$rollbackUserId]);
$rollbackState = $rollbackStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect($rollbackState['account_status'] === 'deletion_pending' && $rollbackState['email'] === 'rollback@example.test', 'Failed execution must roll identity changes back.');
$rollbackRequest = $pdo->prepare('SELECT status FROM account_deletion_requests WHERE id = ?');
$rollbackRequest->execute([$rollbackRequestId]);
retentionExpect($rollbackRequest->fetchColumn() === 'pending', 'Failed execution must leave the deletion request pending.');
$rollbackPublicStmt = $pdo->prepare('SELECT requester_display_name FROM help_public_posts WHERE id = ?');
$rollbackPublicStmt->execute([$rollbackPublic['post_id']]);
retentionExpect($rollbackPublicStmt->fetchColumn() === 'Original requester', 'Failed execution must roll public author anonymization back.');

@unlink($technicalFile);
@rmdir($issueDirectory);
@rmdir($avatarDirectory);
@rmdir(dirname($avatarDirectory));
@rmdir(dirname($avatarDirectory, 2));

echo "Account retention integration checks passed; technical content remained intact and failed transactions rolled back.\n";
