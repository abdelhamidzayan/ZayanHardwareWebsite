# Account retention policy — Zayan Hardware

## Required schema before deployment

The retention worker is not a schema migration tool. Before deploying it or scheduling it, verify that the target database has the account and feature schema expected by the application, including:

- `users.account_status` supports `active`, `deletion_pending`, and `deleted`, and the users/profile columns used by the anonymization service exist.
- `account_deletion_requests`, `security_audit_logs`, and `admin_password_resets` exist (created by the full account-permissions schema in `002_final_registration_permissions.sql`).
- `terms_acceptances` exists (migration 013 can create it when absent).
- `push_subscriptions` exists when Web Push is enabled (migration 006).
- `help_public_posts` and the personal-help tables exist when the public help library is enabled (migration 011).

The alternate `002_registration_wizard.sql` file only changes registration/profile columns; by itself it does **not** create the account-deletion and security-audit tables from `002_final_registration_permissions.sql`. These files describe different schema histories and must not be blindly applied one after the other. If the older history was used, first compare the actual schema with the required schema and prepare a dedicated, reviewed upgrade migration. Do not run the worker or enable cron while any required table or account-status value is missing.

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

The scheduled worker changes the existing user row in place:
- Keeps the same \`users.id\`; changes \`account_status\` to \`deleted\`.
- Replaces the username with a generated anonymous handle and the display name with \`عضو محذوف\`.
- Clears email, phone, biography, and workshop name. Technical device specializations, board/fast classification, experience levels, verification status, skill ratings, and assessment history remain unchanged.
- Replaces the password with an unknown random password, resets the role to \`member\`, and disables personal-help requests.
- Clears the custom avatar reference and removes generated private avatar image files from that user's avatar directory.
- Neutralizes Web Push endpoint/key material so the deleted account cannot receive push notifications.
- Keeps terms acceptance and security-audit rows, but clears stored IP hashes and free-form admin password-reset notes.
- Marks the pending deletion request as completed and writes an audit event.

No \`issues\`, \`issue_replies\`, \`issue_attachments\`, \`issue_diagnostic_updates\`, \`issue_results\`, edit-history, reputation, help-library, help-message, or draft rows are deleted or rewritten. Technical file directories are not traversed or cleaned.

## Safety controls

The worker is CLI-only and defaults to **dry-run**. It uses a MySQL advisory lock to prevent two workers from processing at once, locks each due request inside a transaction, verifies that the account is still \`deletion_pending\`, and rolls database changes back if a step fails.

Run a preview first:

\`\`\`bash
php bin/anonymize-deleted-accounts.php --limit=100
\`\`\`

Review the IDs and schedule, take a database backup, then run explicitly:

\`\`\`bash
php bin/anonymize-deleted-accounts.php --execute --limit=100
\`\`\`

A daily scheduler can run after the first manual preview:

\`\`\`cron
40 2 * * * cd /absolute/path/to/ZayanHardwareWebsite && mkdir -p storage/logs && /usr/bin/php bin/anonymize-deleted-accounts.php --execute --limit=100 >> storage/logs/account-retention-worker.log 2>&1
\`\`\`

Use the actual project path and PHP binary on the server. The worker has not been run against production data. CI currently checks PHP syntax; production-scale and live-database behavior still require a staging test before enabling cron.

## Grace period note

The existing request flow disables the account immediately and schedules anonymization for 30 days later. The current application does not yet expose a self-service cancellation screen for a pending request; do not advertise the grace period as a cancellation guarantee until that workflow exists.
