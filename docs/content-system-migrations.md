# Content system database updates

These migrations extend the existing issue/reply records; they do not replace the existing rows or IDs.

Apply only after checking the local database schema and confirming the earlier baseline migrations have already been applied. The repository currently contains two different migration files with the `002` prefix, so do **not** run every SQL file with a blind alphabetical loop.

Apply the new content-system migrations in this order:

1. `004_content_types.sql` — adds the three content types and lifecycle statuses.
2. `005_diagnostic_updates_and_repair_confirmation.sql` — adds the diagnostic timeline and repair confirmation metadata.
3. `006_notifications_and_push_subscriptions.sql` — adds in-app notification and device push-subscription tables.
4. `007_threaded_replies_and_edit_history.sql` — adds one-level threaded replies and reply edit history.
5. `008_inline_media_tokens.sql` — tracks media rendered inline in safe text-token content.

Before applying, confirm the database selected by the local `.env` is the intended development database and take a backup. Do not paste credentials or the complete `.env` file into chat.

After each migration, verify the expected columns/tables exist before proceeding. If a migration fails partway through, stop and inspect the schema; do not blindly rerun it because the `ALTER TABLE ADD COLUMN` statements are intentionally one-time changes.

The migrations preserve existing issue IDs, replies, attachments, issue-result records, edit history, and reputation events. Existing issues default to `help_request`, so old requests remain visible in the new content system.
