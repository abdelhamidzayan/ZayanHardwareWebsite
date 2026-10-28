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
