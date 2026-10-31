# Fresh database installation order

This is the supported **new-install** sequence for Zayan Hardware. The repository originally began with a database that already contained `users` and `technician_profiles`, but those base table definitions were not stored in source control. Migration `000_core_identity_schema.sql` now supplies that missing baseline for a brand-new database.

## Before running

- Create a dedicated empty database with `utf8mb4_unicode_ci`.
- Confirm the selected database name and host explicitly.
- Take a backup before any schema operation on a non-empty database.
- Never point the fresh-install sequence at an existing local or hosted database.
- The files `014_rate_limit_buckets.sql` and `015_deleted_accounts_without_contact.sql` have already been applied to the owner's current local database; do not replay them there.
- These migrations contain DDL. MySQL/MariaDB implicitly commit many DDL statements, so a migration file is not one atomic transaction. If a step fails, stop and inspect the disposable target schema rather than blindly retrying.

## Explicit order

The duplicate `002_` prefix is historical. These two files are **sequential**, not alternatives: the first normalizes account/skill data and prepares profile fields; the second transfers phone and legacy profile data, then removes the old profile columns after preserving their values.

1. `000_core_identity_schema.sql`
2. `001_issue_discussion.sql`
3. `002_final_registration_permissions.sql`
4. `002_registration_wizard.sql`
5. `003_featured_home_issues.sql`
6. `004_content_types.sql`
7. `005_diagnostic_updates_and_repair_confirmation.sql`
8. `006_notifications_and_push_subscriptions.sql`
9. `007_threaded_replies_and_edit_history.sql`
10. `008_inline_media_tokens.sql`
11. `009_soft_delete_posts.sql`
12. `010_diagnostic_update_history.sql`
13. `011_profiles_personal_help_and_publication.sql`
14. `012_post_composer_drafts_and_device_catalog.sql`
15. `013_terms_acceptance_audit.sql`
16. `014_rate_limit_buckets.sql`
17. `015_deleted_accounts_without_contact.sql`
18. `016_protect_technical_history.sql`
19. `017_block_physical_content_delete.sql`
20. `018_storage_recovery_tasks.sql`

`013` intentionally uses `CREATE TABLE IF NOT EXISTS`: the terms table is created by the registration migration, and this later file also repairs installations where that table was absent.

Migration 016 changes foreign-key delete rules to `RESTRICT` for technical content, personal-help history, skill evidence and attribution. Migration 017 adds `BEFORE DELETE` guards to core technical and audit tables, including standalone posts/replies with no dependent rows. Migration 018 creates a durable recovery queue for filesystem compensation failures and protects its audit rows from physical deletion. These migrations do not delete or rewrite existing content. The normal application workflow uses soft deletion and account anonymization. Draft rows/media and transient notification/push records remain governed by their separate lifecycle.

## Verification requirements

After installation, verify:

- `users` and `technician_profiles` exist with the final registration columns.
- Legacy `technician_profiles.phone`, `specialization`, and `experience` columns are gone only after the phone/specialization history transfer has completed.
- Required content, reply, diagnosis, edit-history, help-request, draft, device-catalog, notification, push, terms, audit, and rate-limit tables exist.
- All application tables use InnoDB.
- Foreign keys and indexes match the current migrations.
- A disposable account can register, log in, and create a technical post with a reply and attachment.

## First administrator

A clean installation runs migrations before a user exists. Therefore the historical `002_final_registration_permissions.sql` bootstrap statement has no account to promote on an empty database. Create the owner's account through registration, then run the guarded operator-only command from the project root:

```bash
DB_NAME=zayan_hardware php bin/bootstrap-first-admin.php --username=OWNER_USERNAME --confirm-database=zayan_hardware --confirm-first-admin
```

Replace `OWNER_USERNAME` and `zayan_hardware` with the actual username and configured database name. The command requires CLI access, exact database-name confirmation, an active account, and zero existing administrator accounts. It serializes concurrent attempts and writes a security-audit record. It refuses to run when any admin already exists. Do not make the first public registrant an administrator automatically.

## Existing installations

Do not apply `000` or replay the sequence against an existing database. Existing installations must be assessed from their live schema and applied migration history. In particular, migrations `014` and `015` must not be rerun against the owner's already-migrated local database.
