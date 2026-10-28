-- Zayan Hardware: enable the three supported post types without replacing legacy issue records.
-- Run once against the intended application database after backing it up.
ALTER TABLE issues
    ADD COLUMN content_type ENUM('help_request','technical_post','discussion') NOT NULL DEFAULT 'help_request' AFTER user_id,
    MODIFY device_model VARCHAR(120) NULL,
    MODIFY category VARCHAR(40) NULL,
    MODIFY status ENUM('open','in_progress','awaiting_confirmation','resolved','closed_unresolved') NOT NULL DEFAULT 'open',
    ADD KEY idx_issues_type_status_created (content_type, status, created_at);
