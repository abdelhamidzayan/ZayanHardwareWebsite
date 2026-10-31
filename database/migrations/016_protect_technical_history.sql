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
    ADD CONSTRAINT fk_issues_accepted_reply
        FOREIGN KEY (accepted_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issues_repair_confirmer
        FOREIGN KEY (repair_confirmed_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issues_manufacturer
        FOREIGN KEY (manufacturer_id) REFERENCES device_manufacturers(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issues_device_model
        FOREIGN KEY (device_model_id) REFERENCES device_models(id) ON DELETE RESTRICT;

-- A post cannot be physically removed while any attachment, reply, edit history,
-- result, or diagnostic record refers to it.
ALTER TABLE issue_attachments
    DROP FOREIGN KEY fk_issue_attachments_issue,
    ADD CONSTRAINT fk_issue_attachments_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_replies
    DROP FOREIGN KEY fk_issue_replies_issue,
    DROP FOREIGN KEY fk_issue_replies_parent,
    ADD CONSTRAINT fk_issue_replies_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issue_replies_parent
        FOREIGN KEY (parent_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT;

-- Votes, edits and final repair results are historical evidence, not disposable
-- dependent rows.
ALTER TABLE reply_votes
    DROP FOREIGN KEY fk_reply_votes_reply,
    DROP FOREIGN KEY fk_reply_votes_user,
    ADD CONSTRAINT fk_reply_votes_reply
        FOREIGN KEY (reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_reply_votes_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE issue_edit_history
    DROP FOREIGN KEY fk_issue_history_issue,
    ADD CONSTRAINT fk_issue_history_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_results
    DROP FOREIGN KEY fk_issue_results_issue,
    DROP FOREIGN KEY fk_issue_results_reply,
    DROP FOREIGN KEY fk_issue_results_reviewer,
    ADD CONSTRAINT fk_issue_results_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issue_results_reply
        FOREIGN KEY (helpful_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_issue_results_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE issue_diagnostic_updates
    DROP FOREIGN KEY fk_issue_diagnostic_updates_issue,
    ADD CONSTRAINT fk_issue_diagnostic_updates_issue
        FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE RESTRICT;

ALTER TABLE issue_reply_edit_history
    DROP FOREIGN KEY fk_issue_reply_history_reply,
    ADD CONSTRAINT fk_issue_reply_history_reply
        FOREIGN KEY (issue_reply_id) REFERENCES issue_replies(id) ON DELETE RESTRICT;

ALTER TABLE issue_diagnostic_update_edit_history
    DROP FOREIGN KEY fk_diagnostic_update_history_update,
    ADD CONSTRAINT fk_diagnostic_update_history_update
        FOREIGN KEY (diagnostic_update_id) REFERENCES issue_diagnostic_updates(id) ON DELETE RESTRICT;

-- User expertise and moderation records must not disappear with a physical user
-- delete. Account closure is handled through the existing anonymization workflow.
ALTER TABLE user_device_specializations
    DROP FOREIGN KEY fk_device_specialization_user,
    ADD CONSTRAINT fk_device_specialization_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE user_skill_domains
    DROP FOREIGN KEY fk_user_skill_domain_user,
    ADD CONSTRAINT fk_user_skill_domain_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE user_skill_ratings
    DROP FOREIGN KEY fk_user_skill_rating_user,
    ADD CONSTRAINT fk_user_skill_rating_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE skill_tests
    DROP FOREIGN KEY fk_skill_tests_creator,
    ADD CONSTRAINT fk_skill_tests_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE skill_test_attempts
    DROP FOREIGN KEY fk_skill_attempt_user,
    ADD CONSTRAINT fk_skill_attempt_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE account_deletion_requests
    DROP FOREIGN KEY fk_account_deletion_handler,
    ADD CONSTRAINT fk_account_deletion_handler
        FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE security_audit_logs
    DROP FOREIGN KEY fk_security_audit_actor,
    DROP FOREIGN KEY fk_security_audit_target,
    ADD CONSTRAINT fk_security_audit_actor
        FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_security_audit_target
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE RESTRICT;

-- Preserve device-catalog approval attribution and the catalog references stored
-- on technical issues.
ALTER TABLE device_models
    DROP FOREIGN KEY fk_device_models_creator,
    DROP FOREIGN KEY fk_device_models_approver,
    ADD CONSTRAINT fk_device_models_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_device_models_approver
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE device_model_suggestions
    DROP FOREIGN KEY fk_device_model_suggestions_submitter,
    DROP FOREIGN KEY fk_device_model_suggestions_reviewer,
    ADD CONSTRAINT fk_device_model_suggestions_submitter
        FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_device_model_suggestions_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT;

-- Private help conversations, their attachments and consent/publication history
-- must remain attached to both participants and their original request.
ALTER TABLE help_requests
    DROP FOREIGN KEY fk_help_requests_requester,
    DROP FOREIGN KEY fk_help_requests_helper,
    DROP FOREIGN KEY fk_help_requests_closed_by,
    ADD CONSTRAINT fk_help_requests_requester
        FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_requests_helper
        FOREIGN KEY (helper_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_requests_closed_by
        FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_messages
    DROP FOREIGN KEY fk_help_messages_request,
    DROP FOREIGN KEY fk_help_messages_sender,
    ADD CONSTRAINT fk_help_messages_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_messages_sender
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_attachments
    DROP FOREIGN KEY fk_help_attachments_request,
    DROP FOREIGN KEY fk_help_attachments_message,
    DROP FOREIGN KEY fk_help_attachments_uploader,
    ADD CONSTRAINT fk_help_attachments_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_attachments_message
        FOREIGN KEY (message_id) REFERENCES help_request_messages(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_attachments_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_request_publications
    DROP FOREIGN KEY fk_help_publication_request,
    DROP FOREIGN KEY fk_help_publication_proposer,
    ADD CONSTRAINT fk_help_publication_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_publication_proposer
        FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE RESTRICT;

ALTER TABLE help_public_posts
    DROP FOREIGN KEY fk_help_public_posts_publication,
    DROP FOREIGN KEY fk_help_public_posts_request,
    DROP FOREIGN KEY fk_help_public_posts_requester,
    DROP FOREIGN KEY fk_help_public_posts_helper,
    ADD CONSTRAINT fk_help_public_posts_publication
        FOREIGN KEY (publication_id) REFERENCES help_request_publications(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_public_posts_request
        FOREIGN KEY (request_id) REFERENCES help_requests(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_public_posts_requester
        FOREIGN KEY (requester_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_help_public_posts_helper
        FOREIGN KEY (helper_user_id) REFERENCES users(id) ON DELETE RESTRICT;

-- A user's unfinished technical drafts are retained when an account is closed.
-- The owner may still explicitly delete an individual draft; its staged media rows
-- continue to cascade with that draft through fk_post_draft_media_draft.
ALTER TABLE post_drafts
    DROP FOREIGN KEY fk_post_drafts_user,
    ADD CONSTRAINT fk_post_drafts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT;
