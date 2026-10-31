-- Zayan Hardware consolidated fresh-install schema.
-- MySQL 8.0 / MariaDB 10.4+; run once on a newly created EMPTY database.
-- Do not run on an existing database. No account is automatically promoted to admin.

-- ============================================================================
-- Source: database/migrations/000_core_identity_schema.sql
-- ============================================================================
-- Zayan Hardware: initial identity/profile schema for a brand-new installation.
-- Apply this exactly once before 001_issue_discussion.sql.
-- Existing installations must NOT apply this file; it is only the missing
-- historical baseline required by the later migrations.

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(20) NOT NULL,
    display_name VARCHAR(60) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('technician','expert','member','moderator','admin') NOT NULL DEFAULT 'technician',
    account_status VARCHAR(32) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status_created (account_status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technician_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(20) NULL,
    specialization VARCHAR(50) NULL,
    experience ENUM('lt1','1-3','3-5','5-10','gt10') NULL,
    workshop_name VARCHAR(100) NULL,
    biography TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_technician_profiles_user (user_id),
    CONSTRAINT fk_technician_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/001_issue_discussion.sql
-- ============================================================================
-- Zayan Hardware: issue details and technical discussion.
-- Run once against the database configured in .env.
CREATE TABLE IF NOT EXISTS issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    device_model VARCHAR(120) NOT NULL,
    category VARCHAR(40) NOT NULL,
    description TEXT NOT NULL,
    measurements TEXT NULL,
    status ENUM('open','resolved','closed_unresolved') NOT NULL DEFAULT 'open',
    accepted_reply_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_issues_status_created (status, created_at),
    KEY idx_issues_user (user_id),
    CONSTRAINT fk_issues_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_issue_attachments_issue (issue_id, created_at),
    CONSTRAINT fk_issue_attachments_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_replies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_issue_replies_issue_created (issue_id, created_at),
    KEY idx_issue_replies_user (user_id),
    CONSTRAINT fk_issue_replies_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE issues
    ADD CONSTRAINT fk_issues_accepted_reply
    FOREIGN KEY (accepted_reply_id) REFERENCES issue_replies(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS reply_votes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reply_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    vote_type ENUM('helpful') NOT NULL DEFAULT 'helpful',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reply_vote_user (reply_id, user_id),
    KEY idx_reply_votes_user (user_id),
    CONSTRAINT fk_reply_votes_reply FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_edit_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    editor_id BIGINT UNSIGNED NOT NULL,
    previous_title VARCHAR(180) NOT NULL,
    previous_description TEXT NOT NULL,
    previous_measurements TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_issue_history_issue_created (issue_id, created_at),
    CONSTRAINT fk_issue_history_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_history_editor FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_results (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_id BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NOT NULL,
    was_repaired TINYINT(1) NOT NULL,
    final_cause TEXT NOT NULL,
    repair_action TEXT NULL,
    helpful_reply_id BIGINT UNSIGNED NULL,
    review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_issue_results_issue_created (issue_id, created_at),
    KEY idx_issue_results_review (review_status, created_at),
    CONSTRAINT fk_issue_results_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_issue_results_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_issue_results_reply FOREIGN KEY (helpful_reply_id) REFERENCES issue_replies(id) ON DELETE SET NULL,
    CONSTRAINT fk_issue_results_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent reputation ledger: only approved moderation workflows should insert positive events.
CREATE TABLE IF NOT EXISTS reputation_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    event_key VARCHAR(190) NOT NULL,
    points INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reputation_event_key (event_key),
    KEY idx_reputation_user (user_id, created_at),
    CONSTRAINT fk_reputation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/002_final_registration_permissions.sql
-- ============================================================================
-- Zayan Hardware: final registration, profile, authorization and assessment schema.
-- Review and back up the database before running this migration.
-- Target: MariaDB 10.4+. Run once against the configured database.

-- Expand the role enum first so the data conversion accepts the new member value.
ALTER TABLE users MODIFY role ENUM('technician','expert','member','moderator','admin') NOT NULL DEFAULT 'member';

-- Normalize legacy roles and account states to the approved model.
UPDATE users SET role = CASE
    WHEN role IN ('admin', 'moderator') THEN role
    ELSE 'member'
END;
UPDATE users SET account_status = 'active'
WHERE account_status = 'pending_verification';
UPDATE users SET account_status = 'suspended'
WHERE account_status = 'restricted';

ALTER TABLE users
    MODIFY email VARCHAR(190) NULL,
    MODIFY role ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
    MODIFY account_status ENUM('active','suspended','banned','deletion_pending','deleted') NOT NULL DEFAULT 'active';

-- First administrator is assigned only through the guarded owner-only CLI command after registration.
-- A contact method may be email OR phone. Phone stays in the profile table,
-- but must be unique when supplied so it can be used as a login identifier.
UPDATE technician_profiles SET phone = NULL WHERE phone = '';
ALTER TABLE technician_profiles
    MODIFY specialization VARCHAR(50) NULL,
    MODIFY experience ENUM('lt1','1-3','3-5','5-10','gt10') NULL,
    ADD UNIQUE KEY uq_technician_profiles_phone (phone);

-- Terms acceptance must be auditable and versioned.
CREATE TABLE IF NOT EXISTS terms_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    terms_version VARCHAR(40) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_terms_user_accepted (user_id, accepted_at),
    CONSTRAINT fk_terms_acceptance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Separate device coverage from repair-skill domains.
CREATE TABLE IF NOT EXISTS user_device_specializations (
    user_id BIGINT UNSIGNED NOT NULL,
    device_type ENUM('iphone','android') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, device_type),
    CONSTRAINT fk_device_specialization_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skill_domains (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(50) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_domains_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO skill_domains (slug, name_ar, sort_order) VALUES
('power','الباور',1),
('charging','الشحن',2),
('network','الشبكات',3),
('display','الشاشة والداتا والتاتش',4),
('audio','الصوت والميكروفون',5),
('wifi_bluetooth','الواي فاي والبلوتوث',6),
('camera','الكاميرا',7),
('schematics','المخططات وBoardview',8),
('cpu','المعالج واللحام الدقيق',9);

CREATE TABLE IF NOT EXISTS user_skill_domains (
    user_id BIGINT UNSIGNED NOT NULL,
    skill_domain_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, skill_domain_id),
    CONSTRAINT fk_user_skill_domain_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_skill_domain_skill FOREIGN KEY (skill_domain_id) REFERENCES skill_domains(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skill ratings are separate per skill and are derived from approved assessments.
-- Preserve existing single-skill profile data in the normalized skill-domain tables.
INSERT IGNORE INTO user_skill_domains (user_id, skill_domain_id)
SELECT p.user_id, sd.id
FROM technician_profiles p
JOIN skill_domains sd ON sd.slug = p.specialization;

CREATE TABLE IF NOT EXISTS user_skill_ratings (
    user_id BIGINT UNSIGNED NOT NULL,
    skill_domain_id BIGINT UNSIGNED NOT NULL,
    rating DECIMAL(5,2) NOT NULL DEFAULT 0,
    approved_assessments INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, skill_domain_id),
    CONSTRAINT chk_user_skill_rating_range CHECK (rating >= 0 AND rating <= 100),
    CONSTRAINT fk_user_skill_rating_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_skill_rating_skill FOREIGN KEY (skill_domain_id) REFERENCES skill_domains(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_skill_ratings (user_id, skill_domain_id, rating)
SELECT user_id, skill_domain_id, 0 FROM user_skill_domains;

CREATE TABLE IF NOT EXISTS skill_tests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    skill_domain_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '1',
    questions_json LONGTEXT NOT NULL,
    pass_score DECIMAL(5,2) NOT NULL DEFAULT 70,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    cooldown_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_skill_tests_domain_active (skill_domain_id, is_active),
    CONSTRAINT fk_skill_tests_domain FOREIGN KEY (skill_domain_id) REFERENCES skill_domains(id) ON DELETE RESTRICT,
    CONSTRAINT fk_skill_tests_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS skill_test_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    skill_test_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    answers_json LONGTEXT NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_skill_test_attempt_number (user_id, skill_test_id, attempt_number),
    KEY idx_skill_attempt_user_test_date (user_id, skill_test_id, started_at),
    CONSTRAINT fk_skill_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_skill_attempt_test FOREIGN KEY (skill_test_id) REFERENCES skill_tests(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual administrative password resets; no public password-reset endpoint.
CREATE TABLE IF NOT EXISTS admin_password_resets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    admin_id BIGINT UNSIGNED NOT NULL,
    reset_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_admin_password_reset_user (user_id, reset_at),
    CONSTRAINT fk_admin_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_admin_password_reset_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Soft deletion first, with a clear retention window and audit trail.
CREATE TABLE IF NOT EXISTS account_deletion_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','cancelled','completed') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_purge_at DATETIME NULL,
    completed_at DATETIME NULL,
    handled_by BIGINT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_account_deletion_status_date (status, requested_at),
    CONSTRAINT fk_account_deletion_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_account_deletion_handler FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id BIGINT UNSIGNED NULL,
    target_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    details_json LONGTEXT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_security_audit_actor_date (actor_user_id, created_at),
    KEY idx_security_audit_target_date (target_user_id, created_at),
    KEY idx_security_audit_action_date (action, created_at),
    CONSTRAINT fk_security_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_security_audit_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/002_registration_wizard.sql
-- ============================================================================
-- Keep legacy technician data when moving the registration schema forward.
ALTER TABLE users
    MODIFY email VARCHAR(190) NULL,
    ADD COLUMN phone VARCHAR(20) NULL AFTER email,
    ADD UNIQUE KEY uq_users_phone (phone);

-- The pre-wizard schema stores phone on technician_profiles. Copy it before
-- dropping that column; the preceding migration made source phone values unique.
UPDATE users u
JOIN technician_profiles p ON p.user_id = u.id
SET u.phone = p.phone
WHERE (u.phone IS NULL OR u.phone = '')
  AND p.phone IS NOT NULL
  AND p.phone <> '';

ALTER TABLE users
    ADD CONSTRAINT chk_users_contact CHECK (email IS NOT NULL OR phone IS NOT NULL);

-- Add the normalized destinations first so profile expertise and history survive.
ALTER TABLE technician_profiles
    ADD COLUMN device_focus ENUM('iphone','android') NULL AFTER user_id,
    ADD COLUMN tech_level ENUM('fast','board') NOT NULL DEFAULT 'fast' AFTER device_focus,
    ADD COLUMN board_experience ENUM('lt1','1-3','3-5','5-10','gt10') NULL AFTER tech_level,
    ADD COLUMN level_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER board_experience;

-- Preserve the legacy experience value before the old column is removed.
UPDATE technician_profiles
SET board_experience = experience
WHERE experience IS NOT NULL;

-- Preserve the exact legacy specialization string as a historical source value.
-- Known slugs were normalized by 002_final_registration_permissions.sql; values
-- that do not match today's catalog must remain recoverable instead of being lost.
CREATE TABLE IF NOT EXISTS technician_profile_specialization_history (
    user_id BIGINT UNSIGNED NOT NULL,
    specialization_value VARCHAR(50) NOT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_legacy_specialization_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO technician_profile_specialization_history (user_id, specialization_value)
SELECT user_id, specialization
FROM technician_profiles
WHERE specialization IS NOT NULL AND specialization <> '';

-- specialization values were copied to user_skill_domains when they matched a
-- current catalog slug. The source value is also retained in the history table.
ALTER TABLE technician_profiles
    DROP COLUMN specialization,
    DROP COLUMN experience,
    DROP COLUMN phone;
