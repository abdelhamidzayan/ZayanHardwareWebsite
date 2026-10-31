-- Invalidate all previously issued PHP sessions after an administrator resets a password.
-- Existing sessions without auth_version are required to sign in again after deployment.
ALTER TABLE users
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_hash;
