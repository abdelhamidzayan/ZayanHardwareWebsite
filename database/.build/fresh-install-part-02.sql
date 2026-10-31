-- ============================================================================
-- Source: database/migrations/003_featured_home_issues.sql
-- ============================================================================
-- Admin-selected posts for the automatically rotating home-page carousel.
CREATE TABLE IF NOT EXISTS featured_home_issues (
    issue_id BIGINT UNSIGNED NOT NULL,
    selected_by BIGINT UNSIGNED NOT NULL,
    selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (issue_id),
    KEY idx_featured_home_selected_at (selected_at),
    CONSTRAINT fk_featured_home_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_featured_home_admin FOREIGN KEY (selected_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/004_content_types.sql
-- ============================================================================
-- Zayan Hardware: enable the three supported post types without replacing legacy issue records.
-- Run once against the intended application database after backing it up.
ALTER TABLE issues
    ADD COLUMN content_type ENUM('help_request','technical_post','discussion') NOT NULL DEFAULT 'help_request' AFTER user_id,
    MODIFY device_model VARCHAR(120) NULL,
    MODIFY category VARCHAR(40) NULL,
    MODIFY status ENUM('open','in_progress','awaiting_confirmation','resolved','closed_unresolved') NOT NULL DEFAULT 'open',
    ADD KEY idx_issues_type_status_created (content_type, status, created_at);


-- ============================================================================
-- Source: database/migrations/005_diagnostic_updates_and_repair_confirmation.sql
-- ============================================================================
-- Zayan Hardware: diagnostic timeline and explicit owner confirmation.
-- Run once after 004_content_types.sql. Existing issue/reply/result rows are preserved.
ALTER TABLE issues
    ADD COLUMN repair_confirmed_at DATETIME NULL AFTER closed_at,
    ADD COLUMN repair_confirmed_by BIGINT UNSIGNED NULL AFTER repair_confirmed_at,
    ADD KEY idx_issues_repair_confirmed_by (repair_confirmed_by),
    ADD CONSTRAINT fk_issues_repair_confirmer
        FOREIGN KEY (repair_confirmed_by) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS issue_diagnostic_updates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_issue_diagnostic_updates_issue_created (issue_id, created_at, id),
    KEY idx_issue_diagnostic_updates_user (user_id),
    CONSTRAINT fk_issue_diagnostic_updates_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_diagnostic_updates_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/006_notifications_and_push_subscriptions.sql
-- ============================================================================
-- Zayan Hardware: private in-app notifications and per-device Web Push subscriptions.
-- Run once after the preceding content/lifecycle migrations.
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    issue_id BIGINT UNSIGNED NULL,
    reply_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(500) NOT NULL,
    target_path VARCHAR(255) NOT NULL,
    event_key CHAR(64) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_event_key (event_key),
    KEY idx_notifications_user_read_created (user_id, is_read, created_at),
    KEY idx_notifications_issue_created (issue_id, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_reply FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    content_encoding VARCHAR(30) NOT NULL DEFAULT 'aes128gcm',
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
    KEY idx_push_subscriptions_user (user_id),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
