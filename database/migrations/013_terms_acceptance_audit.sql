-- Ensure explicit registration consent can be audited even when an older
-- installation used the alternate 002_registration_wizard.sql migration.
-- Safe when 002_final_registration_permissions.sql already created the table.

CREATE TABLE IF NOT EXISTS terms_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    terms_version VARCHAR(40) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash CHAR(64) NULL,
    PRIMARY KEY (id),
    KEY idx_terms_user_accepted (user_id, accepted_at),
    CONSTRAINT fk_terms_acceptance_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
