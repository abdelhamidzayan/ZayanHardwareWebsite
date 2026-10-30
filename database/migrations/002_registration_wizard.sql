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
