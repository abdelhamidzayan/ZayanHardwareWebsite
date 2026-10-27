ALTER TABLE users
    MODIFY email VARCHAR(190) NULL,
    ADD COLUMN phone VARCHAR(20) NULL AFTER email,
    ADD UNIQUE KEY uq_users_phone (phone);

ALTER TABLE users
    ADD CONSTRAINT chk_users_contact CHECK (email IS NOT NULL OR phone IS NOT NULL);

ALTER TABLE technician_profiles
    DROP COLUMN specialization,
    DROP COLUMN experience,
    DROP COLUMN phone,
    ADD COLUMN device_focus ENUM('iphone','android') NULL AFTER user_id,
    ADD COLUMN tech_level ENUM('fast','board') NOT NULL DEFAULT 'fast' AFTER device_focus,
    ADD COLUMN board_experience ENUM('lt1','1-3','3-5','5-10','gt10') NULL AFTER tech_level,
    ADD COLUMN level_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER board_experience;
