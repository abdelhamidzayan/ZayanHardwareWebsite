# Database Backup, Restore, and Comparison Runbook

This runbook separates a real backup from a proven restore. A backup file is **not verified** until its checksum and compression integrity pass and it restores into a separate database that can be inspected.

## Safety requirements

- Never restore into the original/source database.
- Use a new, uniquely named restore-test database. The `CREATE DATABASE` command below intentionally fails if that name already exists; do not add `IF NOT EXISTS` and do not overwrite an existing restore target.
- Do not run migrations as part of a restore test. A restore is a copy of the database state represented by the backup.
- Do not use `DROP DATABASE`, `TRUNCATE`, or media cleanup as part of this procedure.
- Do not paste credentials into chat, shell history, logs, or documentation. MySQL's `-p` option prompts for the password.
- Keep the compressed backup, checksum, and private media backup protected. A database dump alone does not contain files stored outside the database.

## 1. Verify the previously recorded owner backup

The prior handoff recorded this backup:

- File: `/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz`
- SHA-256: `ec270536ba8bc1c6c30e777734988186a6ed98e134eac04b107ca0680154e276`

On the owner's Kali machine, run:

```bash
BACKUP="/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz"
EXPECTED_SHA256="ec270536ba8bc1c6c30e777734988186a6ed98e134eac04b107ca0680154e276"

test -s "$BACKUP"
printf '%s  %s\n' "$EXPECTED_SHA256" "$BACKUP" | sha256sum --check -
gzip -t "$BACKUP"
```

Both checksum verification and `gzip -t` must pass. This verifies that the file matches the previously recorded hash and is a readable gzip stream; it does **not** yet prove MySQL can restore it.

## 2. Restore into a separate database

First inspect the exact MySQL/MariaDB host and source database name in the local environment without printing secrets. Use the real database name as `SOURCE_DB`, and choose a new restore database name.

Example:

```bash
SOURCE_DB="zayan_hardware"
RESTORE_DB="zayan_hardware_restore_test_20261031"
BACKUP="/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz"

# Use the same DB host/port and an account allowed to create a test database.
# -p prompts for the password; do not put the password in this command.
mysql -h 127.0.0.1 -P 3306 -u DB_USER -p -e \
  "CREATE DATABASE \`$RESTORE_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gzip -dc "$BACKUP" | mysql -h 127.0.0.1 -P 3306 -u DB_USER -p "$RESTORE_DB"
```

Replace `DB_USER` with the configured database username and adjust host/port to match the actual server. Enter the password at the prompt. The restore target must be a **new empty database**. If database creation reports that the name already exists, stop and choose a different name rather than deleting or reusing it.

The source database remains untouched. This procedure restores a copy into the separate target only.

## 3. Compare schema objects and row counts

The repository includes a read-only comparator:

```bash
DB_NAME="$SOURCE_DB" php bin/compare-database-schema.php \
  --compare-to="$RESTORE_DB" --rows
```

The comparator connects using the project's configured DB host, port, user, and password, but reads both databases. It compares:

- base tables and storage engines/collations;
- views;
- columns, types, nullability, defaults, generated expressions, and order;
- indexes and their column order;
- foreign keys and their update/delete rules;
- CHECK constraints;
- triggers;
- stored routines and events;
- row counts for every base table when `--rows` is supplied.

Expected result: all sections report `MATCH` and the command exits with status 0. A mismatch or connection/metadata error must be investigated; do not run corrective migrations against either database as part of this comparison.

**Important limitation:** matching row counts and schema objects do not prove every individual row value is byte-for-byte identical. For a high-assurance recovery drill, also inspect critical business records and attachment references using approved read-only queries, and separately verify the corresponding private media files from their own protected backup.

## 4. GitHub CI backup/restore check

The database workflow now performs an isolated backup → gzip/checksum verification → restore into a newly created disposable schema → schema/row-count comparison on each supported matrix engine:

- MySQL 8.0
- MariaDB 10.4.32
- MariaDB 10.11

CI uses synthetic data and disposable databases only. A successful CI restore proves that the repository's fresh-install fixture can be dumped and restored on that engine; it does not prove the owner's local backup, local media tree, or production database is recoverable.

## Verification record

Record the following separately:

| Check | Result |
|---|---|
| Backup checksum | Pending owner-machine verification |
| gzip integrity | Pending owner-machine verification |
| Restore into a new separate database | Pending owner-machine verification |
| Schema object comparison | Pending owner-machine verification |
| Row-count comparison | Pending owner-machine verification |
| Critical row/attachment-reference spot checks | Pending owner-machine verification |
| CI disposable backup/restore matrix | Added to workflow; awaits executed CI result |

Never mark the owner's backup as restore-verified until the target restore and comparisons actually pass.
