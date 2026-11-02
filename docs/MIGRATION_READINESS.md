# Migration Readiness and Safe Upgrade Policy

## Current decision

The administrator console is a diagnostic surface, not a SQL execution environment. The commands `db status`, `db tables`, `db migration <filename.sql>`, and `db preflight` are read-only. No browser command accepts SQL or applies a migration.

The preflight checks the selected database name and server version, detects duplicate three-digit migration sequence numbers, checks whether a migration ledger exists, and reports whether the current database is empty or already contains base tables. It deliberately does not infer applied migrations.

## Why the historical migration directory is not an execution queue

The historical directory contains two different files numbered `002`:

- `002_final_registration_permissions.sql`
- `002_registration_wizard.sql`

The former also contains historical first-admin promotion logic that is not part of the current fresh-install path. Migration `013_terms_acceptance_audit.sql` is retained for alternate historical upgrade paths, while the consolidated fresh-install schema already creates `terms_acceptances`. Other historical files include non-transactional DDL, trigger creation, foreign-key replacement, and data updates. MySQL/MariaDB DDL may implicitly commit.

Therefore, sorting the directory and executing every file is unsafe. The consolidated `database/fresh_install.sql` is only for a newly created empty database and must never be run against an existing database.

## Existing database adoption

If `schema_migrations` is absent and the database contains tables, applied history is UNKNOWN. Do not create a ledger by copying filenames from Git, by looking at the latest application commit, or by assuming that expected tables prove every migration ran.

Before adopting an existing database into a managed migration process:

1. Take a full database backup and a separate private-media backup.
2. Verify the database backup checksum and gzip integrity.
3. Restore the backup into a newly created, separate database.
4. Compare the source and restored schema objects and row counts; inspect critical technical records and attachment references.
5. Capture the live schema metadata: tables, views, columns, indexes, foreign keys, checks, triggers, routines, and events.
6. Compare that metadata with the exact schema baseline represented by the current application release.
7. Resolve every mismatch and historical branch ambiguity before recording a baseline.
8. Only then introduce a new managed ledger with an explicit, reviewed baseline marker. Do not mark historical SQL files as individually applied unless there is evidence for each operation.

No live database is changed by these steps until a separately reviewed migration plan is approved.

## Forward migration policy

New upgrades use a single, unique, monotonically increasing sequence after the historical set. Until the managed CLI runner is ready, owner-approved migrations are added to `database/migrations/` and applied manually only after verifying the database's exact schema version. Each migration must be forward-only and must not rewrite or replay historical files. Each migration must have:

- a unique filename and immutable SHA-256 checksum;
- explicit prerequisites and expected preconditions;
- a documented impact on existing data and technical-history retention;
- a postcondition query or schema assertion;
- a backup/restore and failure-recovery procedure;
- MySQL and supported MariaDB compatibility coverage;
- an explicit record of partial failure, because DDL is not reliably transactional.

A future runner should be CLI-only, use a database advisory lock, verify the ledger and checksums, refuse unknown or drifted states, stop on the first error, and never automatically retry a partially applied DDL migration. The web diagnostic console remains read-only. Manual SQL migrations are run only through a trusted local SQL client or CLI after confirming the target schema.

## Commands available

- `db status` — database identity, server version, historical filenames, and supported ledger status.
- `db tables` — table and view names.
- `db migration <filename.sql>` — read one bounded SQL file for review.
- `db preflight` — read-only safety preflight, including duplicate sequence-number detection and a hard stop for an existing database without a ledger.

## Explicit prohibitions

- Never run `database/fresh_install.sql` on an existing database.
- Never replay migrations 000–019 automatically.
- Never backfill a ledger from filenames or assumptions.
- Never drop, truncate, overwrite, or recreate the live database as a recovery shortcut.
- Never physically delete technical posts, replies, attachments, schematics, diagnostics, or audit history during migration work.
- Never claim the owner's database or private media is backed up or restore-verified based on CI fixtures.


## Managed migration ledger baseline (fresh installs only)

The consolidated fresh-install SQL now creates `app_migration_ledger` and writes exactly one explicit marker: `__baseline_fresh_install_1_0_0__`. Its SHA-256 value fingerprints the fixed baseline identity string `zayan-hardware:fresh-install:1.0.0`; it is not presented as a checksum of the complete SQL file. The marker records application version 1.0.0, UTC applied time, zero baseline execution duration, and `is_baseline=1`.

The ledger schema reserves the fields required by the managed runner: migration filename, SHA-256 checksum, applied timestamp, execution status, application version, execution duration in milliseconds, failure details, and baseline marker. It also stores applying/failed/interrupted states so a future runner can stop for explicit recovery rather than retrying DDL blindly.

This marker is emitted only when `database/fresh_install.sql` creates a new empty database. It must never be copied into an existing installation merely because the tables look similar. Existing databases without this ledger remain UNKNOWN/BLOCKED until the separately documented backup, isolated restore, schema/data comparison, private-media verification, and explicit baseline-adoption review are complete.

The historical `database/migrations/000–019` files remain immutable evidence. Owner-approved forward migrations continue in that directory's numbered sequence while the managed runner is unfinished; the runner-specific directory is reserved for its later adoption. Migration 020 adds original-audio metadata and is included in the consolidated fresh-install schema. It must not be applied after `fresh_install.sql` has already created those columns.
