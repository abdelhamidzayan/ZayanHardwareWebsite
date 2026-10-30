-- Permit account anonymization to clear both personal contact fields.
-- A non-deleted account must still have at least one usable contact method.
ALTER TABLE users
    DROP CONSTRAINT chk_users_contact,
    ADD CONSTRAINT chk_users_contact
        CHECK (email IS NOT NULL OR phone IS NOT NULL OR account_status = 'deleted');
