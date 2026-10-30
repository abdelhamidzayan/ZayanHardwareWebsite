-- Zayan Hardware: member avatars, personal help conversations and consent-based public sharing.
-- Apply once after migration 006. Private message attachments stay outside the public web root.

ALTER TABLE users
    ADD COLUMN avatar_key VARCHAR(40) NOT NULL DEFAULT 'technician-blue',
    ADD COLUMN avatar_path VARCHAR(255) NULL,
    ADD COLUMN accept_help_requests TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS help_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requester_id BIGINT UNSIGNED NOT NULL,
    helper_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(180) NOT NULL,
    status ENUM('pending','active','closed','declined') NOT NULL DEFAULT 'pending',
    closed_by BIGINT UNSIGNED NULL,
    closed_at DATETIME NULL,
    last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_help_requests_requester_status (requester_id, status, updated_at),
    KEY idx_help_requests_helper_status (helper_id, status, updated_at),
    CONSTRAINT fk_help_requests_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_requests_helper FOREIGN KEY (helper_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_requests_closed_by FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_help_requests_distinct_members CHECK (requester_id <> helper_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_request_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_help_messages_request_created (request_id, created_at, id),
    CONSTRAINT fk_help_messages_request FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_request_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    message_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_help_attachments_message (message_id),
    CONSTRAINT fk_help_attachments_request FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_attachments_message FOREIGN KEY (message_id) REFERENCES help_request_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_attachments_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_request_publications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    proposed_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','declined','published') NOT NULL DEFAULT 'pending',
    requester_consented TINYINT(1) NULL,
    requester_show_identity TINYINT(1) NULL,
    helper_show_identity TINYINT(1) NOT NULL,
    public_post_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_help_publication_request (request_id),
    KEY idx_help_publication_status (status, created_at),
    CONSTRAINT fk_help_publication_request FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_publication_proposer FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_public_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    publication_id BIGINT UNSIGNED NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    problem_summary TEXT NOT NULL,
    diagnosis_summary TEXT NOT NULL,
    solution_summary TEXT NOT NULL,
    requester_display_name VARCHAR(80) NULL,
    helper_display_name VARCHAR(80) NULL,
    requester_avatar_key VARCHAR(40) NULL,
    helper_avatar_key VARCHAR(40) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_help_public_post_publication (publication_id),
    KEY idx_help_public_posts_created (created_at, id),
    CONSTRAINT fk_help_public_posts_publication FOREIGN KEY (publication_id) REFERENCES help_request_publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_public_posts_request FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
