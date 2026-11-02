# Fresh database installation

The supported fresh-install path is now **one SQL file**:

`database/fresh_install.sql`

It creates the complete Zayan Hardware schema on MySQL 8.0+ or MariaDB 10.4+.

## New database setup

1. Create a dedicated empty database using `utf8mb4` and `utf8mb4_unicode_ci`.
2. Select that database in phpMyAdmin or your SQL client.
3. Execute the entire `database/fresh_install.sql` file once.
4. Configure the same database credentials in the application's environment.
5. Register the trusted owner account.
6. Promote that account using the guarded owner-only CLI command below.

Example database creation:

```sql
CREATE DATABASE zayan_hardware
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Then from the project root:

```bash
DB_NAME=zayan_hardware php bin/bootstrap-first-admin.php --username=OWNER_USERNAME --confirm-database=zayan_hardware --confirm-first-admin
```

Replace the username and database name with the actual values. The command requires an active account, exact database-name confirmation, and zero existing administrators. It serializes concurrent attempts and records a security-audit event. **The fresh-install SQL never promotes the first registered account automatically.**

## Important safety rules

- This is a **fresh install only**. Never run it against an existing database or a database containing data.
- Backups and media archives are separate from schema creation. This SQL does not restore old posts, attachments, or private media.
- MySQL/MariaDB implicitly commit DDL. If execution fails, inspect the disposable target. For a genuinely new empty target, drop and recreate only that target before retrying; do not blindly replay partial DDL.
- All application tables use InnoDB and utf8mb4. The schema includes identity/registration, technical posts, replies, votes, diagnosis timeline, edit history, repair outcomes, notifications, push subscriptions, personal help, consent-based publication, device catalog, drafts/media, rate limiting, account lifecycle/security audit, storage recovery tasks, session-version invalidation, retention foreign keys, and physical-delete protection triggers.
- Technical posts, replies, attachments, diagnostic history, skill evidence, consent records, and audit records are retained. Use application soft-delete/anonymization workflows rather than physical DELETE.
- No database is created or modified automatically by deployment or by the PHP application.

## Incremental migration history

The numbered files under `database/migrations/` are retained as historical incremental migrations and for legacy/upgrade testing. The duplicate `002_` prefix is historical and those two files are sequential in the old upgrade path. **Do not execute the numbered sequence in addition to `fresh_install.sql` on a new database.**

The consolidated file includes the final schema through migration 020, including separate original and rendered audio metadata. Migration 020 is included directly in the fresh-install schema. The duplicate terms table creation in migration 013 is omitted because it is already created in migration 002. The historical oldest-user auto-promotion in migration 002 is intentionally not part of the new-install SQL; first-admin assignment must use the guarded command above.

## Verification

GitHub Actions runs the single-file fresh install against MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11, then checks required tables, final profile columns, auth_version, foreign keys, retention rules, physical-delete triggers, first-admin bootstrap, and representative technical-content writes.

Owner-machine acceptance remains separate: restore any available old database backup only into a separately named disposable database, and restore private media only into an isolated directory. Never overwrite the new live database or private storage tree during verification.
