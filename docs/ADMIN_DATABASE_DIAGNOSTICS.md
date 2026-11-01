# Admin database and migration diagnostics

The administrator diagnostic console includes a read-only database inspection path:

- `db status`: reports the connected database name and server version, enumerates historical SQL files separately from managed files under `database/managed_migrations/`, and checks the `app_migration_ledger`.
- `db tables`: lists table and view names from the selected database.
- `db migration 019_session_version_invalidation.sql`: displays a bounded migration file for review.

## Important migration-state limitation

The current project keeps numbered incremental SQL files as historical upgrade artifacts and uses `database/fresh_install.sql` for a new empty database. The existing deployed databases may not have a migration ledger. If `app_migration_ledger` is absent, the console explicitly reports the applied state as **UNKNOWN**. It must not infer that a migration ran from its filename, current Git commit, or presence of expected tables.

The managed ledger is accepted only when all required metadata columns exist and the fresh-install baseline identity is valid. A recorded filename is evidence of a recorded operation, not proof that the live schema still matches the migration. `db status` compares each managed file's SHA-256 with its recorded checksum and reports drift. The console does not create, repair, or backfill the ledger.

## Safe operational sequence

1. Open the console and run `db status` and `db tables`.
2. Confirm the database name is the intended environment before interpreting any output.
3. Read the candidate SQL with `db migration <filename.sql>` and review its prerequisites, DDL behavior, foreign keys, triggers, and data-retention impact.
4. Take a verified backup and establish a rollback/recovery plan before any schema change.
5. Apply a migration only through a separately reviewed deployment procedure against the intended database. Do not run the fresh-install SQL against an existing database.
6. Re-run the read-only checks and verify application behavior after the separately performed change.

## Security boundary

These commands do not accept SQL, do not execute migration files, do not modify tables, and do not create migration history. They use the application's configured PDO connection and information_schema read queries only. Database credentials and configuration files remain blocked from file browsing.

A future automated migration runner requires a separately designed migration ledger/bootstrap strategy for existing installations, checksum and ordering rules, per-engine DDL handling (MySQL/MariaDB DDL may implicitly commit), backup/restore gates, locking, failure recovery, and explicit environment approval. It must not be added as an unrestricted SQL command to the browser console.


## Managed ledger diagnostics

`db status` now reports only files under `database/managed_migrations/` as candidates for future managed execution. It checks each recorded migration's SHA-256 checksum against the current file and reports checksum drift, unresolved execution states, and orphan ledger records. The historical `database/migrations/000–019` files are listed only as historical artifacts and are never treated as pending managed migrations.

`db preflight` verifies the exact fresh-install baseline marker and checks for unresolved `applying`, `failed`, or `interrupted` ledger states. Both commands remain read-only. A baseline marker is created by `database/fresh_install.sql` only; it is not a shortcut for adopting an existing database.
