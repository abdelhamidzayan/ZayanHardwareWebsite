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
