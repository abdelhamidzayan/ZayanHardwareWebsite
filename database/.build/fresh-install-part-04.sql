-- ============================================================================
-- Source: database/migrations/011_profiles_personal_help_and_publication.sql
-- ============================================================================
-- Zayan Hardware: member avatars, personal help conversations and consent-based public sharing.
-- Apply once after migration 010 (the latest existing schema migration). Private message attachments stay outside the public web root.

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
    requester_last_seen_at DATETIME NULL,
    helper_last_seen_at DATETIME NULL,
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
    KEY idx_help_messages_unread (request_id, sender_id, created_at),
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
    draft_title VARCHAR(180) NOT NULL,
    problem_summary TEXT NOT NULL,
    diagnosis_summary TEXT NOT NULL,
    solution_summary TEXT NOT NULL,
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
    requester_user_id BIGINT UNSIGNED NULL,
    helper_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_help_public_post_publication (publication_id),
    KEY idx_help_public_posts_created (created_at, id),
    CONSTRAINT fk_help_public_posts_publication FOREIGN KEY (publication_id) REFERENCES help_request_publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_public_posts_request FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_help_public_posts_requester FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_help_public_posts_helper FOREIGN KEY (helper_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/012_post_composer_drafts_and_device_catalog.sql
-- ============================================================================
-- Zayan Hardware: unified technical-post composer, device catalog and resilient drafts.
-- Apply once after migration 011, only after taking a database backup.
-- This file is committed for review; it has NOT been executed against any database.

CREATE TABLE IF NOT EXISTS device_manufacturers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(60) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    logo_path VARCHAR(255) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_device_manufacturers_slug (slug),
    KEY idx_device_manufacturers_active_order (is_active, sort_order, display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO device_manufacturers (slug, display_name, sort_order) VALUES
('apple', 'Apple', 10),
('samsung', 'Samsung', 20),
('infinix', 'Infinix', 30),
('tecno', 'Tecno', 40),
('xiaomi', 'Xiaomi', 50),
('redmi', 'Redmi', 55),
('poco', 'POCO', 60),
('oppo', 'OPPO', 70),
('realme', 'realme', 80),
('vivo', 'vivo', 90),
('huawei', 'Huawei', 100),
('honor', 'HONOR', 110),
('motorola', 'Motorola', 120),
('nokia', 'Nokia', 130),
('oneplus', 'OnePlus', 140);

CREATE TABLE IF NOT EXISTS device_models (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    manufacturer_id BIGINT UNSIGNED NOT NULL,
    commercial_name VARCHAR(140) NOT NULL,
    model_code VARCHAR(120) NULL,
    aliases TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_device_models_manufacturer_active (manufacturer_id, is_active, commercial_name),
    KEY idx_device_models_model_code (model_code),
    FULLTEXT KEY ft_device_models_names (commercial_name, model_code, aliases),
    CONSTRAINT fk_device_models_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES device_manufacturers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_device_models_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_device_models_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_model_suggestions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    manufacturer_id BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    commercial_name VARCHAR(140) NOT NULL,
    model_code VARCHAR(120) NULL,
    aliases TEXT NULL,
    note VARCHAR(500) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    approved_model_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_device_model_suggestions_status_created (status, created_at, id),
    KEY idx_device_model_suggestions_manufacturer (manufacturer_id, status),
    CONSTRAINT fk_device_model_suggestions_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES device_manufacturers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_device_model_suggestions_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_device_model_suggestions_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_device_model_suggestions_model FOREIGN KEY (approved_model_id) REFERENCES device_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE issues
    ADD COLUMN manufacturer_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD COLUMN device_model_id BIGINT UNSIGNED NULL AFTER manufacturer_id,
    ADD COLUMN details_mode ENUM('text','audio') NOT NULL DEFAULT 'text' AFTER measurements,
    ADD COLUMN steps_json JSON NULL AFTER details_mode,
    ADD COLUMN audio_path VARCHAR(255) NULL AFTER steps_json,
    ADD COLUMN audio_mime_type VARCHAR(100) NULL AFTER audio_path,
    ADD COLUMN audio_file_size BIGINT UNSIGNED NULL AFTER audio_mime_type,
    ADD COLUMN audio_duration_ms INT UNSIGNED NULL AFTER audio_file_size,
    ADD COLUMN audio_trim_start_ms INT UNSIGNED NULL AFTER audio_duration_ms,
    ADD COLUMN audio_trim_end_ms INT UNSIGNED NULL AFTER audio_trim_start_ms,
    ADD KEY idx_issues_manufacturer_model (manufacturer_id, device_model_id),
    ADD CONSTRAINT fk_issues_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES device_manufacturers(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_issues_device_model FOREIGN KEY (device_model_id) REFERENCES device_models(id) ON DELETE SET NULL;

ALTER TABLE issue_attachments
    ADD COLUMN original_file_path VARCHAR(255) NULL AFTER file_path,
    ADD COLUMN original_mime_type VARCHAR(100) NULL AFTER original_file_path,
    ADD COLUMN rendered_file_path VARCHAR(255) NULL AFTER original_mime_type,
    ADD COLUMN rendered_mime_type VARCHAR(100) NULL AFTER rendered_file_path,
    ADD COLUMN editor_state JSON NULL AFTER rendered_mime_type,
    ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER editor_state;

CREATE TABLE IF NOT EXISTS post_drafts (
    id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    payload_json JSON NOT NULL,
    last_synced_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_post_drafts_user_updated (user_id, updated_at, id),
    CONSTRAINT fk_post_drafts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_draft_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id CHAR(36) NOT NULL,
    media_uuid CHAR(36) NOT NULL,
    media_kind ENUM('image','video','audio') NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    rendered_path VARCHAR(255) NULL,
    rendered_mime_type VARCHAR(100) NULL,
    rendered_sha256 CHAR(64) NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    editor_state JSON NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_post_draft_media_uuid (media_uuid),
    KEY idx_post_draft_media_order (draft_id, sort_order, id),
    CONSTRAINT fk_post_draft_media_draft FOREIGN KEY (draft_id) REFERENCES post_drafts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/014_rate_limit_buckets.sql
-- ============================================================================
-- Persistent atomic rate-limit buckets, shared by all PHP workers.
CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket VARCHAR(190) NOT NULL,
    window_started_at DATETIME NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (bucket),
    KEY idx_rate_limit_buckets_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/015_deleted_accounts_without_contact.sql
-- ============================================================================
-- Permit account anonymization to clear both personal contact fields.
-- A non-deleted account must still have at least one usable contact method.
ALTER TABLE users
    DROP CONSTRAINT chk_users_contact,
    ADD CONSTRAINT chk_users_contact
        CHECK (email IS NOT NULL OR phone IS NOT NULL OR account_status = 'deleted');

-- The retention worker repeatedly selects due requests by status and schedule.
ALTER TABLE account_deletion_requests
    ADD KEY idx_account_deletion_due (status, scheduled_purge_at, id);
