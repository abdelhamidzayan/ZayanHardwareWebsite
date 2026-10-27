-- Zayan Hardware: final registration, profile, authorization and assessment schema.
-- Review and back up the database before running this migration.
-- Target: MariaDB 10.4+. Run once against the configured database.

START TRANSACTION;

-- Normalize legacy roles and account states to the approved model.
UPDATE users SET role = CASE
    WHEN role IN ('admin', 'moderator') THEN role
    ELSE 'member'
END;
UPDATE users SET account_status = 'active'
WHERE account_status = 'pending_verification';
UPDATE users SET account_status = 'active'
WHERE account_status = 'restricted';

ALTER TABLE users
    MODIFY email VARCHAR(190) NULL,
    MODIFY role ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
    MODIFY account_status ENUM('active','suspended','banned','deletion_pending','deleted') NOT NULL DEFAULT 'active';

-- A contact method may be email OR phone. Phone stays in the profile table,
-- but must be unique when supplied so it can be used as a login identifier.
ALTER TABLE technician_profiles
    MODIFY specialization VARCHAR(50) NULL,
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

COMMIT;
