# Content system database updates

These migrations extend the issue/reply records; they do not replace existing IDs or rewrite technical content.

## Fresh installations

Use the full, explicit sequence in [Fresh database installation order](../database/FRESH_INSTALL.md). It starts with `000_core_identity_schema.sql`, which supplies the `users` and `technician_profiles` baseline that was historically provisioned outside this repository.

The two `002_` files are **sequential** in the supported fresh-install path:

1. `002_final_registration_permissions.sql` normalizes roles/account state, creates normalized skill and consent tables, and preserves the legacy skill mapping.
2. `002_registration_wizard.sql` transfers phone data from `technician_profiles` into `users`, preserves legacy specialization values, and then removes the superseded profile columns.

The CI workflow tests this exact sequence against disposable MySQL and MariaDB databases. The shared `002` numeric prefix is historical, so never use a blind alphabetical migration loop.

## Existing installations

Do not apply the fresh-install sequence to an existing database. Inspect the live schema and migration state first; file presence alone does not prove that a migration ran. DDL statements may commit implicitly, so a partially failed migration must be investigated before any retry.

The content-system migrations are applied after the initial issue schema:

- `004_content_types.sql` — content types and lifecycle statuses.
- `005_diagnostic_updates_and_repair_confirmation.sql` — diagnostic timeline and repair confirmation metadata.
- `006_notifications_and_push_subscriptions.sql` — notifications and Web Push subscriptions.
- `007_threaded_replies_and_edit_history.sql` — threaded replies and reply edit history.
- `008_inline_media_tokens.sql` — safe inline-media token references.
- `009_soft_delete_posts.sql` — non-destructive post deletion metadata.
- `010_diagnostic_update_history.sql` — diagnostic edit history and soft deletion.

Profile, composer and consent additions follow migration 010:

- `011_profiles_personal_help_and_publication.sql`
- `012_post_composer_drafts_and_device_catalog.sql`
- `013_terms_acceptance_audit.sql`
- `014_rate_limit_buckets.sql`
- `015_deleted_accounts_without_contact.sql`
- `016_protect_technical_history.sql` — changes dependent-content and attribution foreign keys to `RESTRICT`, preventing cascading loss of technical history.
- `017_block_physical_content_delete.sql` — adds `BEFORE DELETE` guards on core technical, personal-help and audit tables, including standalone rows that have no dependents.
- `018_storage_recovery_tasks.sql` — stores retry/reconciliation metadata when filesystem compensation cannot safely complete; task rows are retained and no cleanup worker is started automatically.
- `019_session_version_invalidation.sql` — invalidates stale authenticated sessions after security-sensitive account changes.
- `020_audio_original_and_rendered_files.sql` — adds private original-recording path, MIME type, and size fields; the published `audio_path` points to the separately rendered WAV.

Migration 013 uses `CREATE TABLE IF NOT EXISTS` because the terms table is already created by the supported 002 sequence. Migrations 014 and 015 have already been applied to the owner's current local database; never replay them there. Migration 020 is included in `database/fresh_install.sql` for new databases; do not run it after the consolidated fresh-install file. For a database already at 019, apply migration 020 once after verifying the actual schema.

Migration 016 changes referential actions to `RESTRICT`; migration 017 also rejects direct physical deletes from core technical, personal-help and audit tables, even when no child row exists. Routine content deletion remains soft-delete/retention behavior; do not introduce physical content cleanup as part of schema installation. Draft deletion and transient notification/push cleanup remain separate explicit lifecycles.
