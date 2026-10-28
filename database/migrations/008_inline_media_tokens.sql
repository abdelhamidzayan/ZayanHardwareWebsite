-- Zayan Hardware: mark media used inline in safe text-token content.
-- Run once after 007_threaded_replies_and_edit_history.sql.
ALTER TABLE issue_attachments
    ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0 AFTER file_size,
    ADD KEY idx_issue_attachments_inline (issue_id, is_inline);
