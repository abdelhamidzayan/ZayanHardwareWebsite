# Account retention policy — Zayan Hardware

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
- Clears email, phone, biography, workshop name, and other profile identity fields where present.
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
