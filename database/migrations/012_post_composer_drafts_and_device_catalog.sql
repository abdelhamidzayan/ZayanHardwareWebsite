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
