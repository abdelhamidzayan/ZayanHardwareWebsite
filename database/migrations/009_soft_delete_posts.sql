-- Zayan Hardware: soft-delete authored posts while preserving their technical history and relations.
-- Run once after 008_inline_media_tokens.sql.
ALTER TABLE issues
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY idx_issues_deleted_created (deleted_at, created_at);
