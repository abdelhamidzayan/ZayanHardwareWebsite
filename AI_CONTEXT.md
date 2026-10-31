# Zayan Hardware — AI Context and Handoff

> **Start here in every new AI conversation.** Read [Contributor Rules](AGENTS.md), [Current Project State](docs/CURRENT_STATE.md), and [Update Log](UPDATES.md) first. This file is the durable project handoff. For the actual source architecture, open [Architecture and dependency map](docs/architecture-code-map.md). For the automated source graph, read [Graphify](docs/graphify.md). The short reusable prompt is [here](docs/AI_START_PROMPT.md).

## 1. Project identity and scope

- Product: **Zayan Hardware — عصارة الورش**.
- Repository: `abdelhamidzayan/ZayanHardwareWebsite`.
- Purpose: an Arabic-first, RTL technical community and knowledge system for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.
- Stack: custom PHP 8.2+ MVC, MySQL/MariaDB, vanilla JavaScript/CSS, responsive web UI, PWA and Web Push.
- Required architecture: Core → Controllers → Services / Models → Views; route definitions in `config/routes.php`; public document root is `public/`.
- Explicitly out of scope: CRM/customer management and mobile software-repair features, unless the owner explicitly changes the scope.

## 2. Mandatory reading order

1. [Contributor rules](AGENTS.md) — non-negotiable safety, architecture, documentation, and Git rules.
2. [Current project state](docs/CURRENT_STATE.md) — current direction and known verification caveats.
3. [Update log](UPDATES.md) — chronological feature/change history and exact verification claims.
4. [Architecture and dependency map](docs/architecture-code-map.md) — request lifecycle, route/controller/service/model/view relationships.
5. [Graphify guide](docs/graphify.md) — use when architectural dependencies are relevant or a Graphify review is requested.
6. [Security hardening](docs/security-hardening.md) — rate limits, private media, account retention.
7. [Post composer and drafts](docs/post-composer-and-drafts.md) and [profiles/personal help](docs/profiles-personal-help.md) when working on those features.
8. [Account retention policy](docs/account-retention.md) and [content-system migrations](docs/content-system-migrations.md) before touching account lifecycle or schema.
9. Read the actual source at the active branch before changing anything. Documentation is a map, not a substitute for source inspection.

## 3. Source-of-truth boundaries

Never conflate these states:
- GitHub default branch;
- an open pull-request branch;
- the owner's local Kali checkout;
- the running local XAMPP site;
- any hosted/production deployment;
- the live database and its applied migration history.

Always report the exact branch and commit being discussed. A passing CI job does not prove local browser/PWA behavior or production deployment. A migration file existing in Git does not prove it ran on a database.

## 4. GitHub branch and CI snapshot — 2026-10-31

- PR [#16](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/16), “Audit rebuild: fresh install, retention guards and route consistency”, was merged on 2026-10-31 into `audit/content-retention-consistency`.
- Merge commit: `098442813eb5a55e96d5fff27ce127f9b5f08591`. The merge did **not** target `main` and is not a deployment.
- The current completion work is isolated on `phase1/foundation-reliability-20261031`; resolve its live head before future work.
- The most recent successful CI baseline recorded before the latest migrations/content-write additions was `55522a3cc204e961aaf9947773b15ff10321ad02`. It verified migrations 000–017 and the then-current PHP/CLI, Graphify, and database integration checks.
- The merged PR head added migration 018 and additional integration coverage for content writes, draft recovery, device catalog, upload policy, and other paths. The workflows are configured for a clean 000–018 install on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.
- Latest recorded runs for the merged PR head `be51064a6ed551d4e3a48861cd5bab7a64eb8ade` were PHP `41210049298`, Graphify `41210049297`, and database matrix `41210049340`. All completed without executing any workflow steps because the included Actions minutes were exhausted. They are neither passing test results nor application-test failures.
- The owner-reported Actions quota was 2,000/2,000 minutes, with a displayed reset date of 2026-11-01. Recheck live usage and budget before opening a PR or triggering workflows; the owner has not confirmed a $0 spending budget.
- Keep workflow YAML and matrices enabled. Do not rerun jobs that have no executed steps until usage resets or paid Actions usage is explicitly authorized.
- `main`, the merged audit branch, the Phase 1 branch, the owner's local Kali/XAMPP checkout, local database, and any hosted deployment are separate states. Never report a branch merge as a deployment.
- Graphify is static analysis, not a runtime execution trace. Dynamic JavaScript API sites and other unresolved edges require manual source review.
## 5. Owner's local environment — latest reported state

- Local project: `/opt/lampp/htdocs/zayan_hardware`.
- Local branch at the last verified handoff: `local/audit-update-20261031`, based on `46960cf`.
- Local uncommitted/untracked files were reported:
  - modified `.htaccess` with `RewriteBase /zayan_hardware/`;
  - `.htaccess.before-rewrite-fix`;
  - untracked `update.sh`;
  - untracked `zayan_hardware_audit.sql`.
- Preserve these files. Inspect `git status` before every update. Do not reset, clean, overwrite, stage, or discard them without explicit approval.
- A pre-update full database backup was verified at `/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz`; SHA-256: `ec270536ba8bc1c6c30e777734988186a6ed98e134eac04b107ca0680154e276`.
- MariaDB system tables were repaired using `mysql_upgrade`; `mysql.proc` is now the expected 21 columns. Do not enable `event_scheduler`.
- Migrations 014 and 015 were applied locally and verified. Do not rerun them; 015 is not idempotent. Migration 013 already existed. Do not rerun migrations 001–012, especially either 002 migration.
- PHP syntax check passed for all project PHP files. Local Apache rewrite returned HTTP 200 at `http://localhost/zayan_hardware/public/`.
- Storage ACLs were set narrowly for Apache user `daemon`; do not replace them with broad permissions or `chmod 777`.

All local details above are a dated handoff. Recheck the actual machine before acting.

## 6. Technical-content preservation and current write path

- `Issue::deleteIssue()`, `Issue::deleteReply()`, and `Issue::deleteDiagnosticUpdate()` use soft deletion. Physical deletion of protected technical rows is additionally blocked by migrations 016/017 and their foreign-key/trigger guards.
- `Issue::deleteReply()` preserves each child's `parent_reply_id`; a deleted parent is rendered as a text-free tombstone when it still has active children. Do not detach child replies from their original thread.
- `ContentEditorService::pruneUnusedInlineMedia()` is intentionally a no-op. Existing attachments may still be referenced by prior edit-history versions, so removing an inline token from the latest body is not permission to delete the attachment or its file.
- `ContentWriteService` now coordinates issue creation/editing, reply creation/editing, and diagnostic-update creation/editing through a single DB transaction plus staged private-file moves. Notifications occur after the content transaction commits.
- Sensitive administrative writes reviewed later in the audit now revalidate the active admin at the model/service boundary under a row lock and write state changes with security-audit events in one transaction: skill-test creation/status, device-model moderation, and featured-home-post selection. Device-model search is authenticated, rate-limited, limited to 80 query characters, and only searches active models under active manufacturers. The device-catalog integration test and corresponding database CI step passed in the Phase 1 database matrix on all three supported engines.
- When auditing POST routes, follow delegated helpers (for example HelpRequestController::accept()/decline() → respond()) before concluding CSRF/auth/object ownership is missing. Method-local token searches alone can produce false positives.
- Models used by the write service are transaction-aware, so they do not commit/rollback a transaction owned by `ContentWriteService`.
- File compensation is reverse-ordered. A confirmed DB rollback permits removal of newly moved files; a failed cleanup is recorded in `storage_recovery_tasks` (migration 018). An ambiguous commit outcome preserves files and records a pending reconciliation task rather than risking deletion of a file a committed row may reference. There is no automatic recovery worker; pending tasks require an explicit, reviewed reconciliation process.
- `tests/php/content-write-integration.php` covers successful writes, DB rollback, move adapters that return false or throw after creating the destination, later-file-move failure, reply and diagnostic attachment failures, edit-history rollback, failed filesystem compensation, and ambiguous commit handling. The three-engine fresh-install matrix also runs `tests/php/draft-media-recovery-integration.php`, which checks durable recovery tasks for unavailable roots/directories and cross-draft UUID-symlink containment. These integration tests passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11 in run `41219585295`.
- Shared image dimension/pixel checks are implemented in `ImageUploadPolicy` and are used by reviewed issue, draft, avatar and personal-help upload paths. This does not replace MIME validation or byte-size limits.
- Draft publishing already has rollback restoration and ambiguous-commit recovery. Draft media upload/rendered replacement/deletion uses revision checks and reference-aware cleanup; storage-root or UUID-directory unavailability now creates durable pending recovery tasks, cross-draft UUID symlinks are rejected, and dot-only filenames are refused. `tests/php/draft-media-recovery-integration.php` covers the key filesystem boundaries; broader end-to-end CRUD verification remains. Personal-help messages and attachments intentionally use separate commits: each attachment has its own transaction, compensation, and recovery journal, while the UI explicitly reports partial attachment failure. This is not an all-or-nothing message-plus-all-media transaction. `NotificationService` isolates notification/Push failures as best-effort side effects and logs them; there is no durable outbox/retry guarantee. Other review areas remain the full POST authorization matrix, dynamic JS API references, browser/mobile Safari/PWA journeys, and the owner's local backup restore/schema comparison. No real database, private media tree, or owner backup may be modified during these reviews without explicit approval and a verified rollback plan.

## 7. Non-negotiable content preservation

The owner's highest-priority requirement is that technical knowledge must not be lost.

- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or help-library content.
- Do not run account-retention/anonymization workers against real data. Dry-run and disposable-database integration tests only unless the owner explicitly approves otherwise.
- Account lifecycle must preserve stable user IDs and technical attribution; never replace anonymization with physical user deletion.
- Do not prune, bulk-delete, or migrate media without a verified inventory, backup, explicit retention policy, and rollback plan.
- Graphify is source-only and must not read or mutate member uploads, logs, backups, or database contents.

## 8. Engineering workflow and current owner priority

The owner currently wants **feature development first**. The full-system audit, broad Graphify review, and final hardening/documentation sweep are deferred until the planned product features are complete. This is not permission to skip focused checks or safety controls for an individual feature.

For each feature, trace the relevant path:

`HTTP method + route → route parameters → controller → authorization/CSRF/rate limit → service → model/SQL → migration/table/FK → view/layout → JS/CSS/API → storage/logging/notifications → tests`.

Then:
1. inspect the current branch, local working tree, and exact source;
2. reproduce or prove the defect before editing;
3. make the smallest coherent change; avoid unrelated refactoring;
4. add focused regression tests, including failure/rollback cases where relevant;
5. run PHP/JS lint and focused tests relevant to the changed feature. Run broader database/security checks when the change affects those boundaries. Use Graphify when architectural dependencies change or when the owner requests a Graphify review; do not run a full Graphify audit for every small feature;
6. review the complete diff and data-preservation impact;
7. update `UPDATES.md`, `docs/CURRENT_STATE.md` when the overall status changes, and the relevant feature/architecture/migration documentation. Update `README.md` only when top-level setup, navigation, or entry-point information changes.

Never claim a task is complete merely because code was added or CI passed. Separate **implemented**, **tested**, **runtime-verified**, and **deployed** status.


## 9. Phase 1 execution handoff — 2026-10-31

- Owner-approved three-phase program: Phase 1 (database/data integrity/operational reliability), Phase 2 (security/account lifecycle/Graphify), Phase 3 (UI/PWA/cross-device/final acceptance). See [completion plan](docs/COMPLETION_PLAN.md).
- Active Phase 1 branch: `phase1/foundation-reliability-20261031`.
- Always resolve the live Phase 1 branch head before each task. Do not treat a commit SHA copied from an older handoff as the current head.
- Implemented on this branch; the automated Phase 1 CI results are recorded below:
  - `bin/compare-database-schema.php`: read-only schema comparison for tables, views, columns, indexes, foreign keys, CHECK constraints, triggers, stored routines, events, and optional row counts.
  - `app/core/App.php`: complete Authorization and Cookie header redaction plus standalone Bearer, token-field, and session-ID redaction.
  - `tests/php/logging-exception-redaction.php`: synthetic raw Authorization/Cookie/Bearer/query/session-ID/context secrets, app/access/security/audit JSON channels, and log-retention checks.
  - `tests/php/logging-http-access.php`: actual local HTTP request/shutdown access logging, request method/path/status/request ID, and query-token exclusion.
  - `tests/php/logging-http-access.php`: actual PHP built-in HTTP request/shutdown logging, request method/path/status/request ID, and query-token exclusion.
  - `.github/workflows/database-migrations.yml`: disposable backup → gzip/checksum → restore into new database → schema and row-count comparison step, using the CI fixture only.
  - `.github/workflows/php-lint.yml`: PHP syntax suite includes the new comparator CLI help check and expanded logging regression test.
  - `docs/DATABASE_BACKUP_RESTORE.md`: safe owner-machine database restore, schema/row-count comparison, and separate private-media archive/sample-restore procedure.
  - `docs/STORAGE_RECOVERY_RUNBOOK.md`: read-only triage of pending filesystem recovery tasks; no general cleanup worker exists.
- Documentation refreshed: `README.md`, `UPDATES.md`, `docs/CURRENT_STATE.md`, `docs/COMPLETION_PLAN.md`, `docs/DATABASE_BACKUP_RESTORE.md`, and `docs/STORAGE_RECOVERY_RUNBOOK.md`.
- Historical PR #16 jobs executed zero steps due the old private-repository included-minutes limit. After the repo became public, Phase 1 CI ran successfully; see the current results below. CI success does not prove the owner's database/media backups are restored, Apache permissions work locally, or the branch is deployed.
- The owner backup/hash from the prior local handoff is historical evidence of a database backup artifact only. It has not been restored. The existence and integrity of a separate private-media backup are also unverified. Local database restore, schema/row-count comparison, private-media sample restore, Apache file permissions, and HTTP runtime require the owner's actual Kali/XAMPP environment and isolated restore targets.
- Phase 1 automated CI gate passed on `5723fb26f02d14a0d66cc5db43093a8fd1a08aab`. PHP run `41219585375`, Graphify run `41219585292`, and database matrix run `41219585295` all passed. The database matrix covered MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11, including migrations 000–018, integration tests, and backup/restore/schema/row-count comparison.
- Phase 1 is not fully closed: the owner database backup has not been restored into a separate local test database; the separate private-media archive/sample restore is unverified; actual Apache `daemon` log-write permissions remain to be tested on Kali/XAMPP. Do not describe these local checks as CI-tested.
- PR #17 is a draft targeting `audit/content-retention-consistency`, not `main`. It is not merged or deployed. Resolve the live branch head before further work.
