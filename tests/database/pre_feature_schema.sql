-- Minimal pre-feature schema fixture for migration integration tests.
-- It models the existing tables that migrations 011 and 012 alter or reference.
-- This is a disposable test schema, not a production bootstrap file.

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    content_type ENUM('technical_post','discussion','help_request') NOT NULL DEFAULT 'technical_post',
    title VARCHAR(180) NOT NULL,
    device_model VARCHAR(120) NOT NULL,
    category VARCHAR(40) NULL,
    description TEXT NOT NULL,
    measurements TEXT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_test_issues_user (user_id),
    CONSTRAINT fk_test_issues_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE issue_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    is_inline TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_test_attachments_issue (issue_id),
    CONSTRAINT fk_test_attachments_issue FOREIGN KEY (issue_id) REFERENCES issues(id),
    CONSTRAINT fk_test_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
