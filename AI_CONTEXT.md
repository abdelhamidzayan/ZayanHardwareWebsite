# Zayan Hardware — AI Context and Handoff

> **Start here in every new AI conversation.** This file is the durable project handoff. For the actual source architecture, open [Architecture and dependency map](docs/architecture-code-map.md). For the automated source graph, read [Graphify](docs/graphify.md).

## 1. Project identity and scope

- Product: **Zayan Hardware — عصارة الورش**.
- Repository: `abdelhamidzayan/ZayanHardwareWebsite`.
- Purpose: an Arabic-first, RTL technical community and knowledge system for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.
- Stack: custom PHP 8.2+ MVC, MySQL/MariaDB, vanilla JavaScript/CSS, responsive web UI, PWA and Web Push.
- Required architecture: Core → Controllers → Services / Models → Views; route definitions in `config/routes.php`; public document root is `public/`.
- Explicitly out of scope: CRM/customer management and mobile software-repair features, unless the owner explicitly changes the scope.

## 2. Mandatory reading order

1. `AGENTS.md` — non-negotiable safety and contribution rules.
2. [Architecture and dependency map](docs/architecture-code-map.md) — request lifecycle, route/controller/service/model/view relationships.
3. [Graphify guide](docs/graphify.md) — generate/check the machine-readable source graph and understand its limitations.
4. [Security hardening](docs/security-hardening.md) — rate limits, private media, account retention.
5. [Post composer and drafts](docs/post-composer-and-drafts.md) and [profiles/personal help](docs/profiles-personal-help.md) when working on those features.
6. [Account retention policy](docs/account-retention.md) and [content-system migrations](docs/content-system-migrations.md) before touching account lifecycle or schema.
7. Read the actual source at the active branch before changing anything. Documentation is a map, not a substitute for source inspection.

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

- Active audit branch: `audit/system-rebuild-20261031`.
- Pull request: [#16](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/16), titled “Audit rebuild: fresh install, retention guards and route consistency”.
- PR #16 is **Draft, open, and unmerged**. Its base is `audit/content-retention-consistency`; it is not a direct merge to `main`, and nothing on this branch should be described as deployed.
- The audit branch receives frequent commits. Do not trust any SHA copied from an older handoff; resolve the live head from PR #16 before every new session or change.
- Last confirmed green CI baseline: `55522a3cc204e961aaf9947773b15ff10321ad02`, with PHP/CLI/security/JavaScript run [41180453075](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453075), Graphify run [41180453042](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453042), and fresh-install matrix run [41180453039](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453039).
- That green baseline predates the latest commits on PR #16. It verified migrations 000–017 and the earlier test set; it does **not** verify migration 018 or the latest content-write service/test changes.
- The current migration workflow is configured to install migrations 000–018 and run `tests/php/content-write-integration.php`, `tests/php/device-catalog-integration.php`, and the other database suites on the disposable `zayan_fresh_install_test` database for MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11. The PHP lint workflow includes all tracked PHP files; Graphify checks graph generation/determinism. The latest code additions remain **CI-unverified** until those jobs pass on their exact commit.
- To avoid duplicate Actions consumption, each workflow now triggers on `push` only for `main` and on `pull_request` for branch review, with a concurrency group per PR/ref and `cancel-in-progress: true`. This is a trigger optimization, not removal or disabling of workflows.
- The owner reported GitHub Actions usage at **2,000 / 2,000 included minutes**, with the account notice showing reset on **2026-11-01**. The owner has not confirmed that a $0 Actions spending budget is configured. Do not manually rerun/dispatch workflows before the displayed reset or explicit owner authorization. Keep workflow YAML files intact; follow [the Actions usage policy](AGENTS.md#github-actions-and-usage-budget).
- A green historical workflow run proves only the tests and commit it actually executed. It does not prove current branch state, local XAMPP/Kali behavior, PWA/Safari behavior, or deployment.
- Graphify is static analysis, not a runtime call trace. Resolve its outstanding dynamic JavaScript API links manually and review every warning; never treat a missing graph edge as proof that no dependency exists.


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
- Sensitive administrative writes reviewed later in the audit now revalidate the active admin at the model/service boundary under a row lock and write state changes with security-audit events in one transaction: skill-test creation/status, device-model moderation, and featured-home-post selection. Device-model search is authenticated, rate-limited, limited to 80 query characters, and only searches active models under active manufacturers. The new device-catalog integration test and corresponding database CI step are implemented but not yet executed on the current head.
- When auditing POST routes, follow delegated helpers (for example HelpRequestController::accept()/decline() → respond()) before concluding CSRF/auth/object ownership is missing. Method-local token searches alone can produce false positives.
- Models used by the write service are transaction-aware, so they do not commit/rollback a transaction owned by `ContentWriteService`.
- File compensation is reverse-ordered. A confirmed DB rollback permits removal of newly moved files; a failed cleanup is recorded in `storage_recovery_tasks` (migration 018). An ambiguous commit outcome preserves files and records a pending reconciliation task rather than risking deletion of a file a committed row may reference. There is no automatic recovery worker; pending tasks require an explicit, reviewed reconciliation process.
- `tests/php/content-write-integration.php` covers successful writes, DB rollback, later-file-move failure, reply and diagnostic attachment failures, edit-history rollback, failed filesystem compensation, and ambiguous commit handling. The workflow includes this test on the three-engine fresh-install matrix, but the newest code is not yet CI-verified because the Actions quota was exhausted.
- Shared image dimension/pixel checks are implemented in `ImageUploadPolicy` and are used by reviewed issue, draft, avatar and personal-help upload paths. This does not replace MIME validation or byte-size limits.
- Draft publishing already has rollback restoration and ambiguous-commit recovery. Draft media upload/rendered replacement/deletion also uses revision checks and reference-aware cleanup; remaining edge cases include storage-root-unavailable cleanup and broader end-to-end CRUD verification. Personal-help messages and attachments intentionally use separate commits: each attachment has its own transaction, compensation, and recovery journal, while the UI explicitly reports partial attachment failure. This is not an all-or-nothing message-plus-all-media transaction. `NotificationService` isolates notification/Push failures as best-effort side effects and logs them; there is no durable outbox/retry guarantee. Other review areas remain the full POST authorization matrix, dynamic JS API references, browser/mobile Safari/PWA journeys, and the owner's local backup restore/schema comparison. No real database, private media tree, or owner backup may be modified during these reviews without explicit approval and a verified rollback plan.


## 7. Non-negotiable content preservation

The owner's highest-priority requirement is that technical knowledge must not be lost.

- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or help-library content.
- Do not run account-retention/anonymization workers against real data. Dry-run and disposable-database integration tests only unless the owner explicitly approves otherwise.
- Account lifecycle must preserve stable user IDs and technical attribution; never replace anonymization with physical user deletion.
- Do not prune, bulk-delete, or migrate media without a verified inventory, backup, explicit retention policy, and rollback plan.
- Graphify is source-only and must not read or mutate member uploads, logs, backups, or database contents.

## 8. Engineering workflow

For each feature, trace the complete path:

`HTTP method + route → route parameters → controller → authorization/CSRF/rate limit → service → model/SQL → migration/table/FK → view/layout → JS/CSS/API → storage/logging/notifications → tests`.

Then:
1. inspect the current branch and exact source;
2. reproduce or prove the defect before editing;
3. make the smallest coherent fix;
4. add regression tests, including failure/rollback cases;
5. run PHP/JS lint, focused tests, Graphify, and relevant database integration;
6. review the complete diff and data-preservation impact;
7. update this file and the relevant architecture/feature documentation with verified facts only.

Never claim a task is complete merely because code was added or CI passed. Separate **implemented**, **tested**, **runtime-verified**, and **deployed** status.
