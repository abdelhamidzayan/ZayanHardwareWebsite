# Zayan Hardware — Current Project State

> **Last updated:** 2026-10-31. Always recheck live GitHub refs, local checkout, database, and deployment before acting.

## 1. Current goal and delivery plan

The owner approved a **three-phase completion plan**. Complete the remaining stabilization work in controlled phases, report each phase gate, and avoid repeating a full review after every change.

- **Phase 1 — Database, data integrity, and operational reliability:** current phase.
- **Phase 2 — Security, account lifecycle, and Graphify/dependency integrity:** starts after Phase 1.
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
| Ordered fresh-install migrations 000–018 | Documented in `database/FRESH_INSTALL.md`; workflow contains all 20 SQL files in supported order | Implemented; current-head CI pending |
| MySQL/MariaDB matrix | Workflow configured for MySQL 8.0, MariaDB 10.4.32, and 10.11 | Implemented; current-head CI pending |
| Core content write/media recovery | Dedicated integration tests exist for content writes, draft publish/recovery, help media, and reply tombstones | Implemented; current-head CI pending |
| Public/private route and upload checks | Focused route and upload tests exist | Implemented; current-head CI pending |
| Exception log redaction | Complete Authorization/Cookie header redaction, bearer/query-token checks, four-channel JSON checks, and retention regression coverage added | Implemented; PHP test pending execution |
| Access/security/audit channels | Logger provides four channels; real Apache write permissions and HTTP lifecycle still need validation | Runtime check pending |
| Disposable backup/restore comparison | Workflow now dumps, compresses, hashes, restores into a new schema, and compares tables/views/columns/indexes/FKs/checks/triggers/routines/events/row counts | Implemented in CI; awaiting executed workflow result |\n| Owner's full database backup/restore comparison | Previously recorded owner backup/hash has not yet been restored to a separate local test database | Pending owner-machine verification |
| Pending storage recovery reconciliation | Durable recovery records exist; no automatic worker is present | Procedure pending |\n| Read-only schema comparator | Added `bin/compare-database-schema.php` for object and optional row-count comparison between two separate databases | Implemented; syntax/runtime check pending |
| Current-head Actions verification | PR #16 head jobs completed with zero steps because included Actions minutes were exhausted | Not verified |

## 5. Existing technical documentation

- [Architecture and dependency map](architecture-code-map.md)
- [Fresh database installation order](../database/FRESH_INSTALL.md)\n- [Database backup, restore, and comparison runbook](DATABASE_BACKUP_RESTORE.md)
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

- Previous successful CI baseline `55522a3cc204e961aaf9947773b15ff10321ad02` verified migrations 000–017 and the earlier test set, not migration 018 or the latest additions.
- The latest recorded PHP, Graphify, and database matrix jobs for PR #16 executed zero steps because Actions minutes were exhausted. Do not describe that state as passing or failing application tests.
- The account's displayed Actions reset date was 2026-11-01; recheck live quota and spending budget before starting new workflows.
- The owner's local backup and local database work must be performed only on the real machine after checking the exact source/target DB and rollback path. Never restore into the source database.
- Local environment details in [AI_CONTEXT.md](../AI_CONTEXT.md) are dated handoff notes; recheck before acting.

## 7. Latest Phase 1 implementation checkpoint\n\nOn `phase1/foundation-reliability-20261031`, the following Phase 1 work is implemented but not yet test-executed: a read-only schema comparator, an automated disposable backup/restore/checksum/row-count step in the three-engine database workflow, and stronger logging redaction/channel/retention regression coverage. The comparator includes views, routines, and events in addition to tables, columns, indexes, foreign keys, CHECK constraints, triggers, and row counts. No live database was modified.\n\nThe owner-machine restore, Apache permissions, and live HTTP smoke checks remain unverified. GitHub Actions was previously quota-blocked; recheck usage before triggering workflows.\n\n## 8. Working rules for all three phases

1. Follow the relevant scope and pass criteria in [COMPLETION_PLAN.md](COMPLETION_PLAN.md); do not broaden each phase into an unbounded audit.
2. Inspect branch, commit, and `git status` before editing. Preserve modified and untracked local files.
3. Use disposable synthetic databases for CI and isolate any restore test in a newly named database.
4. Run focused regression tests for every fix, then the broader phase gate.
5. Update [UPDATES.md](../UPDATES.md), this file, and the relevant feature/runbook/architecture documents with verified facts.
6. At the end of each phase, report passed checks, failed checks, blocked local checks, changed files, and the next phase.

Do not mark a phase complete while its acceptance criteria remain unverified.
