# Managed forward migrations

This directory is reserved for new, reviewed, forward-only migrations managed by the CLI runner.

- Use a unique monotonically increasing filename sequence beginning at `020_`.
- Never copy or move historical files from `database/migrations/` into this directory.
- Each migration must declare machine-checkable preconditions and postconditions in the runner's approved manifest.
- Files are immutable after first application; the runner must verify SHA-256 against `app_migration_ledger`.
- Do not add an executable migration until the CLI runner, backup/recovery gate, and MySQL/MariaDB integration tests are merged.

No executable migration is currently approved in this directory.
