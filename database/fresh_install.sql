-- Zayan Hardware — Unified Fresh Database Schema
-- Version: 1.0.0
-- Target: MySQL 8.0+ / MariaDB 10.4+
-- PURPOSE: Create the complete application schema in ONE pass on a newly
-- created EMPTY database. This is not an upgrade script and must never be run
-- against an existing database.
--
-- IMPORTANT:
-- 1. Create the database first with utf8mb4 / utf8mb4_unicode_ci.
-- 2. Select that database in your SQL client, then execute this entire file.
-- 3. DDL implicitly commits in MySQL/MariaDB; if execution fails, drop ONLY
--    this disposable empty target database and recreate it before retrying.
-- 4. No user is automatically granted admin. Register the owner, then run
--    bin/bootstrap-first-admin.php with the documented explicit confirmations.
-- 5. All technical posts, replies, diagnostics, attachments, and audit history
--    are protected against physical deletion; use application soft-delete flows.
-- 6. The historical incremental migrations remain for audit/upgrade history.
--
-- Generated from the reviewed 000–019 fresh-install schema, with duplicate
-- 013 omitted (terms_acceptances is already created in 002) and unsafe
-- oldest-user auto-promotion removed from the new-install path.
SET NAMES utf8mb4;

--
-- CONSOLIDATED SECTION 1
--
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


--
-- CONSOLIDATED SECTION 2
--
-- ============================================================================
-- Source: database/migrations/003_featured_home_issues.sql
-- ============================================================================
-- Admin-selected posts for the automatically rotating home-page carousel.
CREATE TABLE IF NOT EXISTS featured_home_issues (
    issue_id BIGINT UNSIGNED NOT NULL,
    selected_by BIGINT UNSIGNED NOT NULL,
    selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (issue_id),
    KEY idx_featured_home_selected_at (selected_at),
    CONSTRAINT fk_featured_home_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_featured_home_admin FOREIGN KEY (selected_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- Source: database/migrations/004_content_types.sql
-- ============================================================================
-- Zayan Hardware: enable the three supported post types without replacing legacy issue records.
-- Run once against the intended application database after backing it up.
ALTER TABLE issues
    ADD COLUMN content_type ENUM('help_request','technical_post','discussion') NOT NULL DEFAULT 'help_request' AFTER user_id,
    MODIFY device_model VARCHAR(120) NULL,
    MODIFY category VARCHAR(40) NULL,
    MODIFY status ENUM('open','in_progress','awaiting_confirmation','resolved','closed_unresolved') NOT NULL DEFAULT 'open',
    ADD KEY idx_issues_type_status_created (content_type, status, created_at);


-- ============================================================================
-- Source: database/migrations/005_diagnostic_updates_and_repair_confirmation.sql
-- ============================================================================
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


-- ============================================================================
-- Source: database/migrations/006_notifications_and_push_subscriptions.sql
-- ============================================================================
-- Zayan Hardware: private in-app notifications and per-device Web Push subscriptions.
-- Run once after the preceding content/lifecycle migrations.
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    issue_id BIGINT UNSIGNED NULL,
    reply_id BIGINT UNSIGNED NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(500) NOT NULL,
    target_path VARCHAR(255) NOT NULL,
    event_key CHAR(64) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_event_key (event_key),
    KEY idx_notifications_user_read_created (user_id, is_read, created_at),
    KEY idx_notifications_issue_created (issue_id, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_reply FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    content_encoding VARCHAR(30) NOT NULL DEFAULT 'aes128gcm',
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_endpoint_hash (endpoint_hash),
    KEY idx_push_subscriptions_user (user_id),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- CONSOLIDATED SECTION 3
--
-- ============================================================================
-- Source: database/migrations/007_threaded_replies_and_edit_history.sql
-- ============================================================================
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


-- ============================================================================
-- Source: database/migrations/008_inline_media_tokens.sql
-- ============================================================================
-- Zayan Hardware: mark media used inline in safe text-token content.
-- Run once after 007_threaded_replies_and_edit_history.sql.
ALTER TABLE issue_attachments
    ADD COLUMN is_inline TINYINT(1) NOT NULL DEFAULT 0 AFTER file_size,
    ADD KEY idx_issue_attachments_inline (issue_id, is_inline);


-- ============================================================================
-- Source: database/migrations/009_soft_delete_posts.sql
-- ============================================================================
-- Zayan Hardware: soft-delete authored posts while preserving their technical history and relations.
-- Run once after 008_inline_media_tokens.sql.
ALTER TABLE issues
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY idx_issues_deleted_created (deleted_at, created_at);


-- ============================================================================
-- Source: database/migrations/010_diagnostic_update_history.sql
-- ============================================================================
-- Zayan Hardware: preserve edit history and allow safe owner-managed diagnostic updates.
-- Run once after 009_soft_delete_posts.sql.
ALTER TABLE issue_diagnostic_updates
    ADD COLUMN updated_at DATETIME NULL AFTER created_at,
    ADD COLUMN deleted_at DATETIME NULL AFTER updated_at,
    ADD KEY idx_issue_diagnostic_updates_deleted (issue_id, deleted_at, created_at);

CREATE TABLE IF NOT EXISTS issue_diagnostic_update_edit_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    diagnostic_update_id BIGINT UNSIGNED NOT NULL,
    editor_id BIGINT UNSIGNED NOT NULL,
    previous_body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_diagnostic_update_history (diagnostic_update_id, created_at),
    CONSTRAINT fk_diagnostic_update_history_update
        FOREIGN KEY (diagnostic_update_id) REFERENCES issue_diagnostic_updates(id) ON DELETE CASCADE,
    CONSTRAINT fk_diagnostic_update_history_editor
        FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- CONSOLIDATED SECTION 4
--
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
    ADD COLUMN audio_original_path VARCHAR(255) NULL AFTER audio_path,
    ADD COLUMN audio_original_mime_type VARCHAR(100) NULL AFTER audio_original_path,
    ADD COLUMN audio_original_file_size BIGINT UNSIGNED NULL AFTER audio_original_mime_type,
    ADD COLUMN audio_mime_type VARCHAR(100) NULL AFTER audio_original_file_size,
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


--
-- CONSOLIDATED SECTION 5
--
-- ============================================================================
-- Source: database/migrations/016_protect_technical_history.sql
-- ============================================================================
-- Zayan Hardware: prevent physical deletion of technical knowledge and attribution.
-- This migration changes referential actions only; it does not delete or update rows.
-- Apply once after migrations 000-015. DDL may commit implicitly, so inspect the
-- target schema before retrying any failed statement.

-- Core issue: prevent deletion of a post's accepted solution and repair confirmer,
-- and retain the selected manufacturer/model references in technical records.
ALTER TABLE issues
    DROP FOREIGN KEY fk_issues_accepted_reply,
    DROP FOREIGN KEY fk_issues_repair_confirmer,
    DROP FOREIGN KEY fk_issues_manufacturer,
    DROP FOREIGN KEY fk_issues_device_model,
    ADD CONSTRAINT fk_016_issues_accepted_reply
        FOREIGN KEY (accepted_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issues_repair_confirmer
        FOREIGN KEY (repair_confirmed_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issues_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES device_manufacturers(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issues_device_model
        FOREIGN KEY (device_model_id) REFERENCES device_models(id) ON DELETE RESTRICT;

-- A post cannot be physically removed while any attachment, reply, edit history,
-- result, or diagnostic record refers to it.
ALTER TABLE issue_attachments
    DROP FOREIGN KEY fk_issue_attachments_issue,
    ADD CONSTRAINT fk_016_issue_attachments_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_replies
    DROP FOREIGN KEY fk_issue_replies_issue,
    DROP FOREIGN KEY fk_issue_replies_parent,
    ADD CONSTRAINT fk_016_issue_replies_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issue_replies_parent
        FOREIGN KEY (parent_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT;

-- Votes, edits and final repair results are historical evidence, not disposable
-- dependent rows.
ALTER TABLE reply_votes
    DROP FOREIGN KEY fk_reply_votes_reply,
    DROP FOREIGN KEY fk_reply_votes_user,
    ADD CONSTRAINT fk_016_reply_votes_reply
        FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_reply_votes_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE issue_edit_history
    DROP FOREIGN KEY fk_issue_history_issue,
    ADD CONSTRAINT fk_016_issue_history_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_results
    DROP FOREIGN KEY fk_issue_results_issue,
    DROP FOREIGN KEY fk_issue_results_reply,
    DROP FOREIGN KEY fk_issue_results_reviewer,
    ADD CONSTRAINT fk_016_issue_results_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issue_results_reply
        FOREIGN KEY (helpful_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_issue_results_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE issue_diagnostic_updates
    DROP FOREIGN KEY fk_issue_diagnostic_updates_issue,
    ADD CONSTRAINT fk_016_issue_diagnostic_updates_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_reply_edit_history
    DROP FOREIGN KEY fk_issue_reply_history_reply,
    ADD CONSTRAINT fk_016_issue_reply_history_reply
        FOREIGN KEY (issue_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT;

ALTER TABLE issue_diagnostic_update_edit_history
    DROP FOREIGN KEY fk_diagnostic_update_history_update,
    ADD CONSTRAINT fk_016_diagnostic_update_history_update
        FOREIGN KEY (diagnostic_update_id) REFERENCES issue_diagnostic_updates(id) ON DELETE RESTRICT;

-- User expertise and moderation records must not disappear with a physical user
-- delete. Account closure is handled through the existing anonymization workflow.
ALTER TABLE user_device_specializations
    DROP FOREIGN KEY fk_device_specialization_user,
    ADD CONSTRAINT fk_016_device_specialization_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE user_skill_domains
    DROP FOREIGN KEY fk_user_skill_domain_user,
    ADD CONSTRAINT fk_016_user_skill_domain_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE user_skill_ratings
    DROP FOREIGN KEY fk_user_skill_rating_user,
    ADD CONSTRAINT fk_016_user_skill_rating_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE skill_tests
    DROP FOREIGN KEY fk_skill_tests_creator,
    ADD CONSTRAINT fk_016_skill_tests_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE skill_test_attempts
    DROP FOREIGN KEY fk_skill_attempt_user,
    ADD CONSTRAINT fk_016_skill_attempt_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE account_deletion_requests
    DROP FOREIGN KEY fk_account_deletion_handler,
    ADD CONSTRAINT fk_016_account_deletion_handler
        FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE security_audit_logs
    DROP FOREIGN KEY fk_security_audit_actor,
    DROP FOREIGN KEY fk_security_audit_target,
    ADD CONSTRAINT fk_016_security_audit_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_security_audit_target
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT;

-- Preserve device-catalog approval attribution and the catalog references stored
-- on technical issues.
ALTER TABLE device_models
    DROP FOREIGN KEY fk_device_models_creator,
    DROP FOREIGN KEY fk_device_models_approver,
    ADD CONSTRAINT fk_016_device_models_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_device_models_approver
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE device_model_suggestions
    DROP FOREIGN KEY fk_device_model_suggestions_submitter,
    DROP FOREIGN KEY fk_device_model_suggestions_reviewer,
    ADD CONSTRAINT fk_016_device_model_suggestions_submitter
        FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_device_model_suggestions_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT;

-- Private help conversations, their attachments and consent/publication history
-- must remain attached to both participants and their original request.
ALTER TABLE help_requests
    DROP FOREIGN KEY fk_help_requests_requester,
    DROP FOREIGN KEY fk_help_requests_helper,
    DROP FOREIGN KEY fk_help_requests_closed_by,
    ADD CONSTRAINT fk_016_help_requests_requester
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_requests_helper
        FOREIGN KEY (helper_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_requests_closed_by
        FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_messages
    DROP FOREIGN KEY fk_help_messages_request,
    DROP FOREIGN KEY fk_help_messages_sender,
    ADD CONSTRAINT fk_016_help_messages_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_attachments
    DROP FOREIGN KEY fk_help_attachments_request,
    DROP FOREIGN KEY fk_help_attachments_message,
    DROP FOREIGN KEY fk_help_attachments_uploader,
    ADD CONSTRAINT fk_016_help_attachments_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_attachments_message
        FOREIGN KEY (message_id) REFERENCES help_request_messages(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_attachments_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_publications
    DROP FOREIGN KEY fk_help_publication_request,
    DROP FOREIGN KEY fk_help_publication_proposer,
    ADD CONSTRAINT fk_016_help_publication_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_publication_proposer
        FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_public_posts
    DROP FOREIGN KEY fk_help_public_posts_publication,
    DROP FOREIGN KEY fk_help_public_posts_request,
    DROP FOREIGN KEY fk_help_public_posts_requester,
    DROP FOREIGN KEY fk_help_public_posts_helper,
    ADD CONSTRAINT fk_016_help_public_posts_publication
        FOREIGN KEY (publication_id) REFERENCES help_request_publications(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_public_posts_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_public_posts_requester
        FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_016_help_public_posts_helper
        FOREIGN KEY (helper_user_id) REFERENCES users(id) ON DELETE RESTRICT;

-- A user's unfinished technical drafts are retained when an account is closed.
-- The owner may still explicitly delete an individual draft; its staged media rows
-- continue to cascade with that draft through fk_post_draft_media_draft.
ALTER TABLE post_drafts
    DROP FOREIGN KEY fk_post_drafts_user,
    ADD CONSTRAINT fk_016_post_drafts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;


-- ============================================================================
-- Source: database/migrations/017_block_physical_content_delete.sql
-- ============================================================================
-- Zayan Hardware: disable physical DELETE for published technical and audit records.
-- Normal application actions update soft-delete/status columns instead.
-- Draft rows/media and transient notifications/push subscriptions remain outside
-- this guard because their explicit lifecycle actions may physically remove them.
-- Apply once after migrations 000-016.

CREATE TRIGGER trg_users_block_physical_delete
BEFORE DELETE ON users
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Physical user deletion is disabled; use account anonymization';

CREATE TRIGGER trg_technician_profiles_block_physical_delete
BEFORE DELETE ON technician_profiles
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technician profile history must be retained';

CREATE TRIGGER trg_issues_block_physical_delete
BEFORE DELETE ON issues
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical posts must be soft-deleted, not physically deleted';

CREATE TRIGGER trg_issue_attachments_block_physical_delete
BEFORE DELETE ON issue_attachments
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical attachments must be retained';

CREATE TRIGGER trg_issue_replies_block_physical_delete
BEFORE DELETE ON issue_replies
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical replies must be soft-deleted, not physically deleted';

CREATE TRIGGER trg_issue_edit_history_block_physical_delete
BEFORE DELETE ON issue_edit_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical post edit history must be retained';

CREATE TRIGGER trg_issue_reply_edit_history_block_physical_delete
BEFORE DELETE ON issue_reply_edit_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical reply edit history must be retained';

CREATE TRIGGER trg_issue_results_block_physical_delete
BEFORE DELETE ON issue_results
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Repair outcome history must be retained';

CREATE TRIGGER trg_issue_diagnostic_updates_block_physical_delete
BEFORE DELETE ON issue_diagnostic_updates
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Diagnostic updates must be soft-deleted, not physically deleted';

CREATE TRIGGER trg_diagnostic_update_history_block_physical_delete
BEFORE DELETE ON issue_diagnostic_update_edit_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Diagnostic edit history must be retained';

CREATE TRIGGER trg_reputation_events_block_physical_delete
BEFORE DELETE ON reputation_events
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Reputation ledger entries are immutable';

CREATE TRIGGER trg_terms_acceptances_block_physical_delete
BEFORE DELETE ON terms_acceptances
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terms acceptance audit records must be retained';

CREATE TRIGGER trg_account_deletion_requests_block_physical_delete
BEFORE DELETE ON account_deletion_requests
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account deletion request history must be retained';

CREATE TRIGGER trg_security_audit_logs_block_physical_delete
BEFORE DELETE ON security_audit_logs
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Security audit records are immutable';

CREATE TRIGGER trg_admin_password_resets_block_physical_delete
BEFORE DELETE ON admin_password_resets
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Administrator password reset audit records must be retained';

CREATE TRIGGER trg_skill_test_attempts_block_physical_delete
BEFORE DELETE ON skill_test_attempts
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Skill assessment attempt history must be retained';

CREATE TRIGGER trg_help_requests_block_physical_delete
BEFORE DELETE ON help_requests
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personal help conversations must be retained as archives';

CREATE TRIGGER trg_help_request_messages_block_physical_delete
BEFORE DELETE ON help_request_messages
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personal help messages must be retained';

CREATE TRIGGER trg_help_request_attachments_block_physical_delete
BEFORE DELETE ON help_request_attachments
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Personal help attachments must be retained';

CREATE TRIGGER trg_help_request_publications_block_physical_delete
BEFORE DELETE ON help_request_publications
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Publication consent history must be retained';

CREATE TRIGGER trg_help_public_posts_block_physical_delete
BEFORE DELETE ON help_public_posts
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Published technical knowledge must be retained';

CREATE TRIGGER trg_device_model_suggestions_block_physical_delete
BEFORE DELETE ON device_model_suggestions
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Device model suggestion and moderation history must be retained';

CREATE TRIGGER trg_profile_specialization_history_no_delete
BEFORE DELETE ON technician_profile_specialization_history
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Legacy technical specialization history must be retained';

CREATE TRIGGER trg_user_skill_ratings_block_delete
BEFORE DELETE ON user_skill_ratings
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verified technical skill ratings must be retained';

CREATE TRIGGER trg_skill_tests_block_physical_delete
BEFORE DELETE ON skill_tests
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Skill assessment definitions must be deactivated, not physically deleted';

CREATE TRIGGER trg_skill_domains_block_physical_delete
BEFORE DELETE ON skill_domains
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Technical skill domain definitions must be retained';

CREATE TRIGGER trg_device_manufacturers_block_delete
BEFORE DELETE ON device_manufacturers
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Device manufacturer catalog entries must be deactivated, not physically deleted';

CREATE TRIGGER trg_device_models_block_physical_delete
BEFORE DELETE ON device_models
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Device model catalog entries must be retained';


-- ============================================================================
-- Source: database/migrations/018_storage_recovery_tasks.sql
-- ============================================================================
-- Zayan Hardware: durable records for filesystem compensation/reconciliation failures.
-- This table stores paths and reason codes only; it never performs file operations.
-- Apply after 017. No existing content rows are modified or removed.

CREATE TABLE storage_recovery_tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aggregate_type VARCHAR(24) NOT NULL,
    aggregate_id BIGINT UNSIGNED NULL,
    file_path VARCHAR(500) NOT NULL,
    file_path_hash CHAR(64) NOT NULL,
    reason_code VARCHAR(80) NOT NULL,
    status ENUM('pending', 'completed', 'blocked') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    last_error_code VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_storage_recovery_file_hash (file_path_hash),
    KEY idx_storage_recovery_status_created (status, created_at),
    KEY idx_storage_recovery_aggregate (aggregate_type, aggregate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TRIGGER trg_storage_recovery_tasks_no_delete
BEFORE DELETE ON storage_recovery_tasks
FOR EACH ROW
SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Storage recovery audit tasks must be retained';


-- ============================================================================
-- Source: database/migrations/019_session_version_invalidation.sql
-- ============================================================================
-- Invalidate all previously issued PHP sessions after an administrator resets a password.
-- Existing sessions without auth_version are required to sign in again after deployment.
ALTER TABLE users
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash;


-- ============================================================================
-- Managed migration ledger baseline (fresh installs only)
-- ============================================================================
-- Existing installations must not receive a synthetic baseline marker.
CREATE TABLE app_migration_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_name VARCHAR(190) NOT NULL,
    checksum CHAR(64) NOT NULL,
    applied_at DATETIME(6) NULL,
    execution_status ENUM('applying','applied','failed','interrupted') NOT NULL,
    application_version VARCHAR(32) NOT NULL,
    execution_duration_ms BIGINT UNSIGNED NULL,
    failure_details TEXT NULL,
    is_baseline TINYINT(1) NOT NULL DEFAULT 0,
    baseline_marker VARCHAR(100) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_migration_ledger_name (migration_name),
    KEY idx_app_migration_ledger_status (execution_status, applied_at),
    KEY idx_app_migration_ledger_baseline (is_baseline, baseline_marker)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_migration_ledger (
    migration_name, checksum, applied_at, execution_status,
    application_version, execution_duration_ms, failure_details,
    is_baseline, baseline_marker
) VALUES (
    '__baseline_fresh_install_1_0_0__',
    SHA2('zayan-hardware:fresh-install:1.0.0', 256),
    UTC_TIMESTAMP(6),
    'applied',
    '1.0.0',
    0,
    NULL,
    1,
    'fresh_install:1.0.0'
);
