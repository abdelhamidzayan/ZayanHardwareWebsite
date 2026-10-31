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
