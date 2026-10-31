-- ============================================================================
-- Source: database/migrations/007_threaded_replies_and_edit_history.sql
-- ============================================================================
-- Zayan Hardware: nested replies and edit history for technician comments.
-- Run once after 006_notifications_and_push_subscriptions.sql.
ALTER TABLE issue_replies
    ADD COLUMN parent_reply_id BIGINT UNSIGNED NULL AFTER issue_id,
    ADD KEY idx_issue_replies_parent (parent_reply_id),
    ADD CONSTRAINT fk_issue_replies_parent
        FOREIGN KEY (parent_reply_id) REFERENCES issue_replies(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS issue_reply_edit_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_reply_id BIGINT UNSIGNED NOT NULL,
    editor_id BIGINT UNSIGNED NOT NULL,
    previous_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_issue_reply_history_reply_created (issue_reply_id, created_at),
    CONSTRAINT fk_issue_reply_history_reply FOREIGN KEY (issue_reply_id) REFERENCES issue_replies(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_reply_history_editor FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/008_inline_media_tokens.sql
-- ============================================================================
-- Zayan Hardware: mark media used inline in safe text-token content.
-- Run once after 007_threaded_replies_and_edit_history.sql.
ALTER TABLE issue_attachments
    ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0 AFTER file_size,
    ADD KEY idx_issue_attachments_inline (issue_id, is_inline);


-- ============================================================================
-- Source: database/migrations/009_soft_delete_posts.sql
-- ============================================================================
-- Zayan Hardware: soft-delete authored posts while preserving their technical history and relations.
-- Run once after 008_inline_media_tokens.sql.
ALTER TABLE issues
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY idx_issues_deleted_created (deleted_at, created_at);


-- ============================================================================
-- Source: database/migrations/010_diagnostic_update_history.sql
-- ============================================================================
-- Zayan Hardware: preserve edit history and allow safe owner-managed diagnostic updates.
-- Run once after 009_soft_delete_posts.sql.
ALTER TABLE issue_diagnostic_updates
    ADD COLUMN updated_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY idx_issue_diagnostic_updates_deleted (issue_id, deleted_at, created_at);

CREATE TABLE IF NOT EXISTS issue_diagnostic_update_edit_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagnostic_update_id BIGINT UNSIGNED NOT NULL,
    editor_id BIGINT UNSIGNED NOT NULL,
    previous_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diagnostic_update_history (diagnostic_update_id, created_at),
    CONSTRAINT fk_diagnostic_update_history_update
        FOREIGN KEY (diagnostic_update_id) REFERENCES issue_diagnostic_updates(id) ON DELETE CASCADE,
    CONSTRAINT fk_diagnostic_update_history_editor
        FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
