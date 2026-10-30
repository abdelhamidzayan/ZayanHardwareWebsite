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
        phone VARCHAR(32) NULL,
        device_focus VARCHAR(32) NOT NULL DEFAULT 'iphone',
        board_experience VARCHAR(32) NOT NULL DEFAULT '5-10',
        specialization VARCHAR(80) NOT NULL DEFAULT 'board',
        experience VARCHAR(32) NOT NULL DEFAULT '5-10',
        workshop_name VARCHAR(120) NOT NULL DEFAULT '',
        biography TEXT NOT NULL,
        tech_level VARCHAR(24) NOT NULL DEFAULT 'board',
        level_verified TINYINT(1) NOT NULL DEFAULT 1,
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
            (user_id, phone, device_focus, board_experience, specialization, experience, workshop_name, biography, tech_level, level_verified)
         VALUES (?, ?, 'iphone', '5-10', 'board', '5-10', 'Owner Workshop', 'Profile biography', 'board', 1)"
    )->execute([$ownerId, '+201000000001']);

    // Expertise and verified assessment history are technical contributions,
    // not account identity; all of them must survive anonymization.
    $powerDomainId = (int) $pdo->query("SELECT id FROM skill_domains WHERE slug = 'power' LIMIT 1")->fetchColumn();
    retentionExpect($powerDomainId > 0, 'The power skill domain must exist in the test schema.');
    $pdo->prepare(
        "INSERT INTO user_device_specializations (user_id, device_type) VALUES (?, 'iphone')"
    )->execute([$ownerId]);
    $pdo->prepare(
        'INSERT INTO user_skill_domains (user_id, skill_domain_id) VALUES (?, ?)'
    )->execute([$ownerId, $powerDomainId]);
    $pdo->prepare(
        'INSERT INTO user_skill_ratings (user_id, skill_domain_id, rating, approved_assessments) VALUES (?, ?, 88.50, 4)'
    )->execute([$ownerId, $powerDomainId]);
    $pdo->prepare(
        'INSERT INTO skill_tests
            (skill_domain_id, title, version, questions_json, pass_score, max_attempts, cooldown_minutes, is_active, created_by)
         VALUES (?, ?, ?, ?, 70, 3, 1440, 1, ?)'
    )->execute([$powerDomainId, 'Retention CI power test', 'retention-v1', '{"questions":[]}', $ownerId]);
    $skillTestId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO skill_test_attempts
            (user_id, skill_test_id, attempt_number, answers_json, score, passed, submitted_at)
         VALUES (?, ?, 1, ?, 92.50, 1, UTC_TIMESTAMP())'
    )->execute([$ownerId, $skillTestId, '{"q1":"answer-a"}']);

    $pdo->prepare(
        "INSERT INTO issues (user_id, title, device_model, description)
         VALUES (?, 'Retained board repair', 'Infinix X605', 'Original technical diagnosis')"
    )->execute([$ownerId]);
    $issueId = (int) $pdo->lastInsertId();

    $privateRoot = App::root('storage/private');
    $avatarDirectory = $privateRoot . '/avatars/' . $ownerId;
    $issueDirectory = $privateRoot . '/issues/' . $issueId;
    if (!is_dir($avatarDirectory) && !mkdir($avatarDirectory, 0770, true) && !is_dir($avatarDirectory)) {
        throw new RuntimeException('Could not create test avatar directory.');
    }
    if (!is_dir($issueDirectory) && !mkdir($issueDirectory, 0770, true) && !is_dir($issueDirectory)) {
        throw new RuntimeException('Could not create test issue directory.');
    }
    $avatarName = str_repeat('a', 40) . '.jpg';
    $avatarPath = $avatarDirectory . '/' . $avatarName;
    $technicalFile = $issueDirectory . '/schematic-board-photo.jpg';
    if (file_put_contents($avatarPath, 'private avatar test') === false
        || file_put_contents($technicalFile, 'technical attachment test') === false) {
        throw new RuntimeException('Could not create test files.');
    }
    $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')
        ->execute(['avatars/' . $ownerId . '/' . $avatarName, $ownerId]);

    $pdo->prepare(
        "INSERT INTO issue_attachments
            (issue_id, uploaded_by, file_path, mime_type, file_size, is_inline)
         VALUES (?, ?, ?, 'image/jpeg', 26, 1)"
    )->execute([$issueId, $ownerId, 'issues/' . $issueId . '/schematic-board-photo.jpg']);
    $attachmentId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO issue_replies (issue_id, user_id, body) VALUES (?, ?, ?)'
    )->execute([$issueId, $helperId, 'Measured VPH_PWR and found a short.']);
    $replyId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO issue_diagnostic_updates (issue_id, user_id, body) VALUES (?, ?, ?)'
    )->execute([$issueId, $ownerId, 'Thermal inspection isolated the shorted capacitor.']);
    $diagnosticId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO issue_results (issue_id, submitted_by, was_repaired, final_cause, repair_action)
         VALUES (?, ?, 1, ?, ?)'
    )->execute([$issueId, $ownerId, 'Shorted capacitor on main rail', 'Replaced capacitor and verified current draw.']);
    $pdo->prepare(
        'INSERT INTO issue_edit_history (issue_id, editor_id, previous_title, previous_description, previous_measurements)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$issueId, $ownerId, 'Previous title', 'Previous technical description', 'Previous measurements']);
    $pdo->prepare(
        'INSERT INTO issue_reply_edit_history (issue_reply_id, editor_id, previous_body) VALUES (?, ?, ?)'
    )->execute([$replyId, $helperId, 'Previous reply with diagnosis']);
    $pdo->prepare(
        'INSERT INTO issue_diagnostic_update_edit_history (diagnostic_update_id, editor_id, previous_body) VALUES (?, ?, ?)'
    )->execute([$diagnosticId, $ownerId, 'Previous diagnostic update']);
    $pdo->prepare(
        "INSERT INTO reply_votes (reply_id, user_id, vote_type) VALUES (?, ?, 'helpful')"
    )->execute([$replyId, $ownerId]);
    $pdo->prepare(
        "INSERT INTO reputation_events (user_id, event_key, points, reason) VALUES (?, ?, 5, 'Test technical contribution')"
    )->execute([$helperId, 'retention-test:' . bin2hex(random_bytes(3))]);

    $draftId = '33333333-3333-4333-8333-333333333333';
    $mediaUuid = '44444444-4444-4444-8444-444444444444';
    $pdo->prepare(
        'INSERT INTO post_drafts (id, user_id, revision, payload_json, last_synced_at)
         VALUES (?, ?, 1, JSON_OBJECT("title", "Retained draft"), UTC_TIMESTAMP())'
    )->execute([$draftId, $ownerId]);
    $pdo->prepare(
        "INSERT INTO post_draft_media
            (draft_id, media_uuid, media_kind, storage_path, mime_type, file_size, sha256)
         VALUES (?, ?, 'image', 'drafts/retention-test/photo.jpg', 'image/jpeg', 128, REPEAT('c', 64))"
    )->execute([$draftId, $mediaUuid]);

    $firstPublic = createPublicHelpPost($pdo, $ownerId, $helperId, 'Public article — owner as requester');
    $secondPublic = createPublicHelpPost($pdo, $helperId, $ownerId, 'Public article — owner as helper');
    $messageInsert = $pdo->prepare(
        'INSERT INTO help_request_messages (request_id, sender_id, body) VALUES (?, ?, ?)'
    );
    $messageInsert->execute([$firstPublic['request_id'], $ownerId, 'Private technical help message']);
    $messageId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO help_request_attachments (request_id, message_id, uploaded_by, file_path, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$firstPublic['request_id'], $messageId, $ownerId, 'help-requests/retention-test/photo.jpg', 'image/jpeg', 128]);

    $pdo->prepare(
        'INSERT INTO terms_acceptances (user_id, terms_version, ip_hash) VALUES (?, ?, ?)'
    )->execute([$ownerId, 'retention-test-v1', str_repeat('b', 64)]);
    $termsId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO security_audit_logs (actor_user_id, target_user_id, action, details_json, ip_hash)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$ownerId, $ownerId, 'test.before_retention', '{"test":true}', str_repeat('d', 64)]);
    $auditId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO admin_password_resets (user_id, admin_id, note) VALUES (?, ?, ?)'
    )->execute([$ownerId, $helperId, 'Contains personal admin note']);
    $resetId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, public_key, auth_token, user_agent, last_used_at)
         VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    )->execute([$ownerId, 'https://push.example.test/owner', str_repeat('e', 64), 'real-public-key', 'real-auth-token', 'Retention CI']);
    $pushId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO account_deletion_requests (user_id, status, scheduled_purge_at, note)
         VALUES (?, 'pending', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE), 'scheduled test')"
    )->execute([$ownerId]);
    $deletionRequestId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE users SET account_status = 'deletion_pending' WHERE id = ?")->execute([$ownerId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

// Editing the self-declared skill list must not reset earned ratings.
App\Models\User::saveOptionalSkills($ownerId, ['power'], 'Updated Workshop');
$editedRatingStmt = $pdo->prepare('SELECT rating, approved_assessments FROM user_skill_ratings WHERE user_id = ? AND skill_domain_id = ?');
$editedRatingStmt->execute([$ownerId, $powerDomainId]);
$editedRating = $editedRatingStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect(
    $editedRating['rating'] === '88.50' && (int) $editedRating['approved_assessments'] === 4,
    'Changing profile skill selections must not delete or reset assessment-backed skill ratings.'
);

$technicalTables = [
    'issues', 'issue_attachments', 'issue_replies', 'issue_diagnostic_updates',
    'issue_results', 'issue_edit_history', 'issue_reply_edit_history',
    'issue_diagnostic_update_edit_history', 'reply_votes', 'reputation_events',
    'help_requests', 'help_request_messages', 'help_request_attachments',
    'help_request_publications', 'help_public_posts', 'post_drafts', 'post_draft_media',
];
$beforeCounts = [];
foreach ($technicalTables as $table) $beforeCounts[$table] = retentionCount($pdo, $table);

ContentEditorService::pruneUnusedInlineMedia($issueId);
retentionExpect(
    (int) $pdo->query('SELECT COUNT(*) FROM issue_attachments WHERE id = ' . $attachmentId)->fetchColumn() === 1,
    'Editing cleanup must not delete a published inline attachment row.'
);
retentionExpect(is_file($technicalFile), 'Editing cleanup must not delete the technical attachment file.');

$preview = AccountRetentionService::run(false, 100);
retentionExpect($preview['mode'] === 'dry-run' && $preview['due'] === 1 && $preview['processed'] === 0, 'Dry-run must report one due request without changing data.');
$stillPending = $pdo->prepare('SELECT account_status, email FROM users WHERE id = ?');
$stillPending->execute([$ownerId]);
$pendingBeforeExecute = $stillPending->fetch(PDO::FETCH_ASSOC);
retentionExpect($pendingBeforeExecute['account_status'] === 'deletion_pending' && $pendingBeforeExecute['email'] === 'owner@example.test', 'Dry-run must not anonymize the account.');

$execution = AccountRetentionService::run(true, 100);
retentionExpect($execution['mode'] === 'execute' && $execution['processed'] === 1 && $execution['errors'] === [], 'One due account must be anonymized without worker errors.');

$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$userStmt->execute([$ownerId]);
$anonymized = $userStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect(is_array($anonymized), 'The original users row and ID must remain.');
retentionExpect($anonymized['account_status'] === 'deleted', 'Account must be marked deleted.');
retentionExpect($anonymized['display_name'] === 'عضو محذوف', 'Public author name must be anonymized.');
retentionExpect(str_starts_with($anonymized['username'], 'deleted_'), 'Username must be replaced by an anonymous handle.');
retentionExpect($anonymized['email'] === null && $anonymized['phone'] === null, 'Email and phone must be cleared.');
retentionExpect($anonymized['avatar_path'] === null && $anonymized['avatar_key'] === 'technician-blue', 'Personal avatar reference must be cleared.');
retentionExpect((int) $anonymized['accept_help_requests'] === 0 && $anonymized['last_login_at'] === null, 'Account access and personal help must be disabled.');

$profileStmt = $pdo->prepare('SELECT phone, workshop_name, biography, level_verified FROM technician_profiles WHERE user_id = ?');
$profileStmt->execute([$ownerId]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect($profile['phone'] === null && $profile['workshop_name'] === '' && $profile['biography'] === '', 'Technician contact and personal profile fields must be anonymized.');
retentionExpect(
    $profile['device_focus'] === 'iphone'
    && $profile['board_experience'] === '5-10'
    && $profile['specialization'] === 'board'
    && $profile['experience'] === '5-10'
    && $profile['tech_level'] === 'board'
    && (int) $profile['level_verified'] === 1,
    'Technical specialization, experience, and verification must remain unchanged.'
);

$deviceStmt = $pdo->prepare('SELECT device_type FROM user_device_specializations WHERE user_id = ?');
$deviceStmt->execute([$ownerId]);
retentionExpect($deviceStmt->fetchAll(PDO::FETCH_COLUMN) === ['iphone'], 'Device specializations must remain unchanged.');
$skillDomainStmt = $pdo->prepare('SELECT skill_domain_id FROM user_skill_domains WHERE user_id = ?');
$skillDomainStmt->execute([$ownerId]);
$userSkillDomainIds = array_map('intval', $skillDomainStmt->fetchAll(PDO::FETCH_COLUMN));
retentionExpect($userSkillDomainIds === [$powerDomainId], 'User skill-domain assignments must remain unchanged.');
$ratingStmt = $pdo->prepare('SELECT rating, approved_assessments FROM user_skill_ratings WHERE user_id = ? AND skill_domain_id = ?');
$ratingStmt->execute([$ownerId, $powerDomainId]);
$rating = $ratingStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect($rating['rating'] === '88.50' && (int) $rating['approved_assessments'] === 4, 'Verified skill ratings must remain unchanged.');
$attemptStmt = $pdo->prepare('SELECT score, passed, answers_json FROM skill_test_attempts WHERE user_id = ? AND skill_test_id = ?');
$attemptStmt->execute([$ownerId, $skillTestId]);
$attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect($attempt['score'] === '92.50' && (int) $attempt['passed'] === 1 && $attempt['answers_json'] === '{"q1":"answer-a"}', 'Skill assessment attempts and answers must remain unchanged.');
$skillTestStmt = $pdo->prepare('SELECT created_by FROM skill_tests WHERE id = ?');
$skillTestStmt->execute([$skillTestId]);
retentionExpect((int) $skillTestStmt->fetchColumn() === $ownerId, 'Technical test authorship must retain the stable user ID.');

$publicStmt = $pdo->prepare(
    'SELECT id, requester_user_id, helper_user_id, requester_display_name, helper_display_name
     FROM help_public_posts WHERE id IN (?, ?) ORDER BY id'
);
$publicStmt->execute([$firstPublic['post_id'], $secondPublic['post_id']]);
$publicRows = $publicStmt->fetchAll(PDO::FETCH_ASSOC);
retentionExpect(count($publicRows) === 2, 'Both published help articles must remain.');
retentionExpect(
    $publicRows[0]['requester_display_name'] === 'عضو محذوف'
    && $publicRows[1]['helper_display_name'] === 'عضو محذوف',
    'Both requester and helper public author snapshots must be anonymized.'
);

$pushStmt = $pdo->prepare('SELECT endpoint, endpoint_hash, public_key, auth_token, user_agent, last_used_at FROM push_subscriptions WHERE id = ?');
$pushStmt->execute([$pushId]);
$push = $pushStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect(str_starts_with($push['endpoint'], 'https://deleted.invalid/'), 'Push endpoint must be neutralized.');
retentionExpect($push['endpoint_hash'] !== str_repeat('e', 64) && $push['public_key'] !== 'real-public-key' && $push['auth_token'] !== 'real-auth-token', 'Push credentials must be neutralized.');
retentionExpect($push['user_agent'] === null && $push['last_used_at'] === null, 'Push metadata must be cleared.');

$termsStmt = $pdo->prepare('SELECT ip_hash FROM terms_acceptances WHERE id = ?');
$termsStmt->execute([$termsId]);
retentionExpect($termsStmt->fetchColumn() === null, 'Terms acceptance row must remain with its IP hash cleared.');
$auditStmt = $pdo->prepare('SELECT ip_hash FROM security_audit_logs WHERE id = ?');
$auditStmt->execute([$auditId]);
retentionExpect($auditStmt->fetchColumn() === null, 'Audit row must remain with its IP hash cleared.');
$resetStmt = $pdo->prepare('SELECT note FROM admin_password_resets WHERE id = ?');
$resetStmt->execute([$resetId]);
retentionExpect($resetStmt->fetchColumn() === null, 'Password reset audit row must remain with free-form note cleared.');

$deletionStmt = $pdo->prepare('SELECT status, completed_at FROM account_deletion_requests WHERE id = ?');
$deletionStmt->execute([$deletionRequestId]);
$deletion = $deletionStmt->fetch(PDO::FETCH_ASSOC);
retentionExpect($deletion['status'] === 'completed' && $deletion['completed_at'] !== null, 'Deletion request must be marked completed.');

retentionExpect(!is_file($avatarPath), 'Private custom avatar must be removed after the account transaction commits.');
retentionExpect(is_file($technicalFile), 'Technical attachment binary must remain on disk.');

$afterCounts = [];
foreach ($technicalTables as $table) $afterCounts[$table] = retentionCount($pdo, $table);
retentionExpect($beforeCounts === $afterCounts, 'No technical post, reply, diagnosis, repair result, attachment, publication, help message, or draft row may be deleted.');

$repeat = AccountRetentionService::run(true, 100);
retentionExpect($repeat['due'] === 0 && $repeat['processed'] === 0, 'Repeated worker execution must not process the same completed request twice.');

// A failure at the final audit insert must roll back every identity and profile change.
$rollbackPublic = createPublicHelpPost($pdo, $rollbackUserId, $helperId, 'Rollback public article');
$pdo->prepare(
    "INSERT INTO technician_profiles
        (user_id, phone, device_focus, board_experience, specialization, experience, workshop_name, biography, tech_level, level_verified)
     VALUES (?, ?, 'android', '1-3', 'board', '1-3', 'Rollback Workshop', 'Keep this profile if transaction fails', 'board', 1)"
)->execute([$rollbackUserId, '+201000000003']);
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
