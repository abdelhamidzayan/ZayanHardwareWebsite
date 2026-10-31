# Account retention policy — Zayan Hardware

## Required schema before deployment

The retention worker is not a schema migration tool. Before deploying it or scheduling it, verify that the target database has the account and feature schema expected by the application, including:

- `users.account_status` supports `active`, `deletion_pending`, and `deleted`, and the users/profile columns used by the anonymization service exist.
- `account_deletion_requests`, `security_audit_logs`, and `admin_password_resets` exist (created by the full account-permissions schema in `002_final_registration_permissions.sql`).
- `terms_acceptances` exists (migration 013 can create it when absent).
- `users.chk_users_contact` permits both contact fields to be NULL only when `account_status = 'deleted'` (migration 015).
- `push_subscriptions` exists when Web Push is enabled (migration 006).
- `help_public_posts` and the personal-help tables exist when the public help library is enabled (migration 011).

The two files share the `002_` prefix but are a **sequential pair**, not alternative migrations. For a database that starts from the pre-registration schema, apply them in this exact order:

1. `002_final_registration_permissions.sql` — creates the account-deletion/security-audit tables and the normalized skill-domain tables, and copies the legacy `technician_profiles.specialization` value into `user_skill_domains`.
2. `002_registration_wizard.sql` — copies legacy `technician_profiles.phone` into `users.phone`, copies `experience` into `board_experience`, archives the exact original specialization string, then removes the old profile columns.

Do not sort or reorder these two files by number alone. The database CI workflow now applies this exact sequence and checks that phone, experience and normalized skill-domain data survive on MySQL 8.0 and MariaDB 10.11. Legacy specialization strings that do not match a current skill-domain slug remain in `technician_profile_specialization_history` for future mapping. This verifies a synthetic pre-registration baseline; it does not prove which migrations have already been applied to the live database.

**Administrative bootstrap warning:** the first migration promotes the oldest user to `admin` only when no admin exists. Before applying it to a real database, inspect the oldest account and verify that it belongs to the trusted platform owner. Do not rely on this automatic promotion without that check.

Apply migration 014 before deploying code that uses the database-backed rate limiter. Apply migration 015 before enabling account anonymization. Do not run the worker or enable cron while any required table, constraint, or account-status value is missing.

## Non-negotiable content rule

Deleting an account must **never delete technical knowledge**. The retention worker does not remove database rows. It keeps the user's stable numeric ID so all attribution and foreign-key relationships remain valid.

The following remain available exactly as content records:
- Published technical posts and help requests.
- Replies, nested discussion, edits, edit history, and diagnostic updates.
- Repair confirmations, repair results, helpful votes, and reputation events.
- Original and rendered images, videos, audio, schematics, PDFs, and their attachment records.
- Published help-library articles and their problem/diagnosis/solution text.
- Personal-help conversation messages and attachments, notifications, and saved drafts.

The public author label becomes **عضو محذوف**. The profile page is no longer available. The public help-library author snapshots are anonymized without changing the technical article text. Retained posts continue using the generic technician avatar.

## What happens after 30 days

**Current behavior:** a deletion request immediately disables the account and schedules a 30-day deadline. However, the release candidate contains no approved CLI entry point, scheduler, or automatic worker. When the deadline passes, the account remains `deletion_pending`; the deadline does not mean anonymization has occurred.

The repository retains an `AccountRetentionService` with a proposed identity-anonymization workflow, but the earlier `bin/anonymize-deleted-accounts.php` command was intentionally removed. Do not invoke the service through ad-hoc PHP, recreate that command, or schedule a cron job without a separate security review, staging acceptance, verified backup/restore, and explicit owner approval.

If a future worker is approved, it must:
- Keep the same `users.id` and all attribution/foreign-key relationships.
- Replace identity fields with an anonymous handle and display name.
- Preserve technical specializations, experience, verification status, skill ratings, and assessment history.
- Neutralize push credentials and handle custom-avatar cleanup safely.
- Keep terms acceptance and security-audit rows while applying the approved personal-metadata minimization policy.
- Mark the pending request completed and write an audit event.

No `issues`, `issue_replies`, `issue_attachments`, `issue_diagnostic_updates`, `issue_results`, edit-history, reputation, help-library, help-message, or draft rows may be deleted or rewritten. Technical file directories must never be traversed or cleaned by account anonymization.

## Safety controls

The service contains safeguards intended for a future controlled worker: it refuses to anonymize the last active administrator, coordinates with the admin roster advisory lock, uses a separate worker lock, locks each due request inside a transaction, rechecks that the account is still `deletion_pending`, and rolls back database changes on failure. These safeguards do **not** make the service executable by themselves.

**Operational state:** no CLI entry point and no cron job are approved or present in the release candidate. Do not run the service manually, add a scheduler, or mark requests completed. A future execution mechanism requires separate review, production-scale and staging tests, a verified backup/restore, and explicit owner approval.

## Grace period note

The existing request flow disables the account immediately and schedules anonymization for 30 days later. A sole active administrator cannot submit this request; the platform must have another active administrator before the first admin can leave. The current application does not yet expose a self-service cancellation screen for a pending request; do not advertise the grace period as a cancellation guarantee until that workflow exists.
