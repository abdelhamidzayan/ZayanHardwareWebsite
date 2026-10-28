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
