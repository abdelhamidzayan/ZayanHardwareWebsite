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
