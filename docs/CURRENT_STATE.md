# Zayan Hardware — Current Project State

> **Last updated:** 2026-10-31. Always recheck live GitHub refs, local checkout, database, and deployment before acting.

## 1. Current goal and delivery plan

The owner approved a **three-phase completion plan**. Complete the remaining stabilization work in controlled phases, report each phase gate, and avoid repeating a full review after every change.

- **Phase 1 — Database, data integrity, and operational reliability:** CI gate passed; owner-machine acceptance checks remain open.
- **Phase 2 — Security, account lifecycle, and Graphify/dependency integrity:** in progress on `phase2/security-graphify-20261031`.
- **Phase 3 — UI, PWA, cross-device acceptance, and final readiness:** starts after Phase 2.

The full scope and acceptance criteria are in [COMPLETION_PLAN.md](COMPLETION_PLAN.md).

## 2. GitHub and release state

- PR [#16](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/16) was merged on 2026-10-31 into `audit/content-retention-consistency`.
- Merge commit: `098442813eb5a55e96d5fff27ce127f9b5f08591`.
- This merge did **not** target `main` and is not evidence of deployment.
- Current Phase 1 work is on `phase1/foundation-reliability-20261031`, based on that merge commit.
- Do not assume the local Kali/XAMPP checkout, `main`, the merged audit branch, and any hosted deployment are synchronized.

## 3. Product boundaries and data preservation

Zayan Hardware is an Arabic-first, RTL technical community for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.

- Keep the existing PHP MVC architecture: Core → Controllers → Services / Models → Views.
- Do not add CRM/customer-management or mobile software-repair scope without owner approval.
- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or approved help knowledge as part of normal account/content operations.
- Preserve stable technical attribution and user IDs. Account retention must follow the documented anonymization/preservation policy, not physical user deletion.
- Destructive database/filesystem operations require explicit approval, a verified backup, and a rollback plan.

## 4. Phase 1 — current checklist

| Work item | Current evidence | Status |
|---|---|---|
| Ordered fresh-install migrations 000–018 | Full clean-install sequence passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11 | CI verified on `5723fb26f02d14a0d66cc5db43093a8fd1a08aab` |
| MySQL/MariaDB matrix | All 3 matrix jobs completed successfully, including the complete integration suite | CI verified |
| Core content write/media recovery | Issue/reply media writes, draft publish outcomes, symlink containment, personal-help media, authorization and reply tombstones passed | CI verified on all 3 engines |
| Public/private route and upload checks | Public/private route policy, image upload policy and PHP/JS syntax suites passed | CI verified |
| Exception log redaction | Authorization/Cookie/Bearer/query/session-ID redaction, four log channels, JSON validity, and retention regression passed | CI verified |
| Access/security/audit channels | Real HTTP request/shutdown logging passed with method/path/status/request ID and query-token exclusion; actual Apache user/ACL remains to be checked on local XAMPP | HTTP integration CI verified; local Apache check pending |
| Disposable backup/restore comparison | Backup → gzip/checksum → restore into a new schema → compare tables/views/columns/indexes/FKs/checks/triggers/routines/events/row counts passed on all 3 engines | CI verified |
| Owner's full database backup/restore comparison | Previously recorded owner backup/hash has not yet been restored to a separate local test database | Pending owner-machine verification |
| Private-media backup and sample restore | A separate archive/path and isolated restore of referenced private files have not been verified | Pending owner-machine verification |
| Pending storage recovery reconciliation | Durable records exist; a read-only/manual triage runbook is now documented; no general automatic worker exists | Procedure documented; reconciliation pending |
| Read-only schema comparator | CLI check plus full schema-object and row-count comparison during restore passed on all 3 engines | CI verified |
| Phase 1 branch CI verification | PHP, Graphify, and 3-engine database workflows all passed on commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab` | All CI gates passed; local acceptance pending |

## 5. Existing technical documentation

- [Architecture and dependency map](architecture-code-map.md)
- [Fresh database installation order](../database/FRESH_INSTALL.md)
- [Database backup, restore, and comparison runbook](DATABASE_BACKUP_RESTORE.md)
- [Storage recovery triage](STORAGE_RECOVERY_RUNBOOK.md)
- [Content system implementation](content-system-implementation.md)
- [Content migrations](content-system-migrations.md)
- [Security hardening](security-hardening.md)
- [Account retention](account-retention.md)
- [Post composer and drafts](post-composer-and-drafts.md)
- [Profiles and personal help](profiles-personal-help.md)
- [Graphify guide](graphify.md)
- [System rebuild audit snapshot](system-rebuild-audit.md)

Documentation is a map, not proof. Inspect the actual code and live database state relevant to the change.

## 6. Verification boundaries and local work

- Historical CI baseline `55522a3cc204e961aaf9947773b15ff10321ad02` covered migrations 000–017 only. The newer Phase 1 commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab` passed the full 000–018 matrix and integration suite.
- PR #16's earlier zero-step runs were caused by exhausted included Actions minutes. After the repository became public, PR #17 successfully executed all current Phase 1 workflows; the current public-repository run is the source of truth.
- The owner database restore and private-media restore remain separate local tasks and must not be confused with the disposable CI restore.
- The owner's local backup and local database work must be performed only on the real machine after checking the exact source/target DB and rollback path. Never restore into the source database.
- Local environment details in [AI_CONTEXT.md](../AI_CONTEXT.md) are dated handoff notes; recheck before acting.

## 7. Latest Phase 1 implementation checkpoint

On `phase1/foundation-reliability-20261031`, the Phase 1 automated gate passed on commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab`:

- PHP lint, CLI boot, rate-limiter API, draft rollback, public-route policy, schema-comparator CLI, HTTP access-log lifecycle, exception redaction/retention, upload policy, and JavaScript syntax all passed.
- Graphify analyzer tests, graph generation, determinism check, and artifact upload passed.
- The full fresh-install sequence 000–018 and all integration suites passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.
- Disposable backup → gzip/checksum → restore into a new schema → schema-object and row-count comparison passed on all three engines.

The owner database restore, private-media archive/sample restore, and real Apache (`daemon`) permissions/log-write checks remain unverified because they require the owner's local Kali/XAMPP environment. No live database or private media tree was modified by CI.

## 8. Phase 2 — current security work

**Branch:** `phase2/security-graphify-20261031`  
**Latest verified commit:** `889dd99c74824674789a138bfc7dd3b9dd30444e`

Implemented and CI-verified on that exact commit:

- Suspended-account login failures return the same public error as invalid credentials; blocked-account attempts consume the same IP/identifier failure buckets.
- Migration 019 adds `users.auth_version`; password reset, account status/reactivation, role changes, deletion requests, and final identity anonymization revoke older sessions.
- Login/session version binding and conditional password rehash prevent stale sessions and an in-flight old-password login from restoring an obsolete hash.
- Regression coverage includes stale sessions, password-reset/login races, suspension/reactivation with an old cookie, generic login errors, and account lifecycle mutation policy.
- Automated CSRF policy scans all 49 declared POST routes and enforces the public POST allow-list (`/login`, `/register`). This is not proof that all object-level authorization is correct.
- Admin updater hardening includes CSRF, per-user rate limiting, active-admin revalidation, fixed command arguments, audit events, and a shared lifecycle lock.
- Graphify generated 706 nodes and 1,646 edges; the review recorded 174 SQL queries, 21 migrations, 15 API call sites, and manual mapping of five dynamic API references.
- The frontend sink review covered 13 JavaScript files and key issue/help/profile/auth/admin templates. Dynamic text is escaped; reviewed `innerHTML` sinks use fixed SVG fragments or fixed PWA guide copy.

**Exact-head CI:** all three workflows completed successfully on `889dd99c74824674789a138bfc7dd3b9dd30444e`:
- [PHP syntax/security tests](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933680) — passed.
- [Graphify dependency graph](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933760) — passed.
- [Database migration integration](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933686) — passed.

**Still open:** full object-level authorization matrix for all routes; broader SQL/input validation review; complete XSS/output-encoding review; upload/private-file lifecycle and containment review; storage-recovery/notification retry behavior; and local updater/sudoers runtime inspection. The external updater script is not the same as the in-app updater controller.

No Phase 2 completion is claimed. PR #18 remains a draft; no merge to `main`, production database migration, or deployment is claimed.

## 9. Local runtime findings (2026-10-31)

The owner checked the Kali/XAMPP host:

- Apache workers run as `daemon`.
- `storage/logs` is `root:root` mode `0775`; `sudo -u daemon test -w storage/logs` failed. Apache therefore cannot create new log files or rotate logs in that directory.
- Existing application/access/security/audit log files are `daemon:daemon` mode `0660`, so existing files are writable by their owner. `permission-test.log` is more permissive (`0664`) and should be reviewed for consistency.
- The `daemon` sudo policy permits only `/usr/bin/bash /home/a/bin/zayan-hardware-update.sh` as user `a`; this is a narrow command rule, not unrestricted sudo.
- The external update script is mode `0700`, owned by `a:a`. It requires branch `main`, a clean tracked worktree, and allows only a small untracked-file exception. The current local checkout is instead `local/audit-update-20261031`, has a modified `.htaccess`, and has untracked files. **Do not run the updater or clean/reset/checkout this worktree.**
- Local HEAD `46960cf9efb33b2b0affa503d5e832725bdba4b4` differs from the Phase 2 CI-verified GitHub candidate. Preserve the local modifications and untracked files.

**Safe next local action:** inspect current ACLs and the application logging/rotation implementation, then grant the Apache service account write access to this log directory only if that matches the deployment policy. Do not use broad `chmod 777`, recursive ownership changes, or run the updater as a workaround. Owner-database restore and private-media restore remain separate pending acceptance checks.

## 9. Working rules for all three phases

1. Follow the relevant scope and pass criteria in [COMPLETION_PLAN.md](COMPLETION_PLAN.md); do not broaden each phase into an unbounded audit.
2. Inspect branch, commit, and `git status` before editing. Preserve modified and untracked local files.
3. Use disposable synthetic databases for CI and isolate any restore test in a newly named database.
4. Run focused regression tests for every fix, then the broader phase gate.
5. Update [UPDATES.md](../UPDATES.md), this file, and the relevant feature/runbook/architecture documents with verified facts.
6. At the end of each phase, report passed checks, failed checks, blocked local checks, changed files, and the next phase.

Do not mark a phase complete while its acceptance criteria remain unverified.
