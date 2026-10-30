-- Pre-registration schema for testing the two sequential 002 migrations.
-- A legacy phone, repair specialization and experience are seeded deliberately
-- so the migration test can verify that no historical profile data is lost.

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    role ENUM('technician','expert','member','moderator','admin') NOT NULL DEFAULT 'technician',
    account_status VARCHAR(32) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technician_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(20) NULL,
    specialization VARCHAR(50) NULL,
    experience VARCHAR(24) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pre_registration_profile_user (user_id),
    CONSTRAINT fk_pre_registration_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (email, role, account_status)
VALUES ('owner@example.test', 'technician', 'active');

INSERT INTO technician_profiles (user_id, phone, specialization, experience)
VALUES (1, '+201000000001', 'power', '5-10');
