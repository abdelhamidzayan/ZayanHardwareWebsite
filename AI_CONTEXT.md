# Zayan Hardware — AI Context and Handoff

> **Start here in every new AI conversation.** Read [Contributor Rules](AGENTS.md), [Current Project State](docs/CURRENT_STATE.md), and [Update Log](UPDATES.md) first. This file is the durable project handoff. For the actual source architecture, open [Architecture and dependency map](docs/architecture-code-map.md). For the automated source graph, read [Graphify](docs/graphify.md). The short reusable prompt is [here](docs/AI_START_PROMPT.md).

## 1. Project identity and scope

- Product: **Zayan Hardware — عصارة الورش**.
- Repository: `abdelhamidzayan/ZayanHardwareWebsite`.
- Purpose: an Arabic-first, RTL technical community and knowledge system for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.
- Stack: custom PHP 8.2+ MVC, MySQL/MariaDB, vanilla JavaScript/CSS, responsive web UI, PWA and Web Push.
- Required architecture: Core → Controllers → Services / Models → Views; route definitions in `config/routes.php`; public document root is `public/`.
- Explicitly out of scope: CRM/customer management and mobile software-repair features, unless the owner explicitly changes the scope.

## Brand identity

- Canonical visual guide: [docs/BRAND_IDENTITY.md](docs/BRAND_IDENTITY.md).
- Primary palette: workshop green `#105023` with cream `#FFFCF3` and ivory `#F8F3E0`; orange `#D75421` is reserved for the strongest CTA.
- CSS source of truth: `public/css/tokens.css`. Preserve the Arabic-first RTL layout and existing responsive/accessibility behavior when extending the UI.

## 2. Mandatory reading order

1. [Contributor rules](AGENTS.md) — non-negotiable safety, architecture, documentation, and Git rules.
2. [Current project state](docs/CURRENT_STATE.md) — current direction and known verification caveats.
3. [Update log](UPDATES.md) — chronological feature/change history and exact verification claims.
4. [Architecture and dependency map](docs/architecture-code-map.md) — request lifecycle, route/controller/service/model/view relationships.
5. [Graphify guide](docs/graphify.md) — use when architectural dependencies are relevant or a Graphify review is requested.
6. [Security hardening](docs/security-hardening.md) — rate limits, private media, account retention.
7. [POST route security matrix](docs/SECURITY_POST_ROUTE_MATRIX.md) — current POST endpoint CSRF and authorization map.
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

- PR [#17](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/17) merged into `main` as `94ad4e077de6d0698ca1f2dad138a9fa9ada62cf`. It integrated the accumulated product feature stack and Phase 1 reliability work.
- PR [#18](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/18) merged into `main` as `dadddde53f394a0fe353b575ec13dbeb85749f6e`. It integrated the Phase 2 session invalidation, POST CSRF policy, focused object-level authorization tests, admin updater hardening, and Graphify/security documentation.
- `main` is now the single authoritative development branch. Start new feature work from the current `main`; do not continue development from the historical Phase 1/Phase 2 branches.
- Phase 1 exact-head workflows passed on `4bf013f81fc71bc32f8b221fefc1c778d00419d8`: PHP [41238247509](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247509), Graphify [41238247478](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247478), database matrix [41238247686](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247686).
- Phase 2 exact tested workflows passed on `c23dde25f2bee53068c85e775ca56fa93f52e0a9`: PHP [41238382181](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382181), Graphify [41238382162](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382162), database matrix [41238382193](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382193).
- Skill-test input/answer exposure follow-up PR #19 is also merged as `bf871bba8de078588a0d17778ed4520199188f96`; exact head `d58282608187d5cbd5af59c66c332b2a7394b20e` passed database [41239306049](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306049), PHP [41239306080](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306080), and Graphify [41239306068](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306068).
- CI used disposable databases/storage. No owner database migration, real private-media restore, or hosted deployment is claimed.
- Historical audit/feature branches are retained temporarily for traceability. Delete them only after verifying their tips are already contained in `main`; never delete a branch with unique commits.

## 5. Owner's local environment — latest reported state

- Local project: `/opt/lampp/htdocs/zayan_hardware`.
- Local branch: `local/audit-update-20261031`, HEAD `46960cf9efb33b2b0affa503d5e832725bdba4b4`. This checkout is not synchronized with the merged GitHub `main`.
- Preserve all local changes and untracked files:
  - modified `.htaccess`;
  - `.htaccess.before-rewrite-fix`;
  - `composer.lock`;
  - `tests/python/__pycache__/` and `tools/graphify/__pycache__/`;
  - `update.sh`;
  - `zayan_hardware_audit.sql`.
- Do not run the host updater, pull, reset, clean, switch branches, or overwrite any of these files until a separate safe reconciliation plan is prepared.
- A previous handoff recorded a candidate database backup at `/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz` with SHA-256 `ec270536ba8bc1c6c30e777734988186a6ed98e134eac04b107ca0680154e276`. This is historical evidence only: its current existence/hash and successful restore have not been rechecked in this session. Never restore it over the source database.
- The project-directory inventory found no database backup or private-media archive; external backup locations were not inspected. A separate private-media backup remains unverified.
- Apache runs as `daemon`. The `storage/logs` access/default ACL was tightened to remove `other` access while retaining daemon write access. Two real create-file tests as `daemon` succeeded and new files inherited `other::---`.
- The external updater script is mode `0700`, owned by `a:a`; sudoers permits only that fixed script as user `a`. It was not run.
- Local database and media restore tests have not been performed. No live DB, media tree, or deployment was modified.

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

The owner has consolidated the accumulated feature work and automated Phase 1/2 baselines into `main` and wants to resume feature development. **Create all new feature branches from the latest `main`** and keep changes focused.

For each feature, trace the relevant path:

`HTTP method + route → route parameters → controller → authorization/CSRF/rate limit → service → model/SQL → migration/table/FK → view/layout → JS/CSS/API → storage/logging/notifications → tests`.

Then:
1. inspect the current `main` head and local working tree;
2. reproduce or prove the defect before editing;
3. make the smallest coherent change; avoid unrelated refactoring;
4. add focused regression tests, including failure/rollback cases where relevant;
5. run PHP/JS lint and focused tests. Run broader database/security checks when the change affects those boundaries. Use Graphify when architecture changes or the owner requests it;
6. review the complete diff and data-preservation impact;
7. update `UPDATES.md`, `docs/CURRENT_STATE.md` when the overall status changes, and relevant feature/architecture/migration docs.

Never claim completion merely because code was added or CI passed. Separate **implemented**, **tested**, **runtime-verified**, and **deployed** status. The main consolidation is not deployment approval; before production rollout, complete the remaining security, backup/media, and browser/PWA acceptance gates.

## 9. Phase 1 execution handoff — 2026-10-31 (historical, merged)

Phase 1's automated reliability baseline is merged into `main` through PR #17. The latest Phase 1 release-candidate workflows passed on `4bf013f81fc71bc32f8b221fefc1c778d00419d8`:
- PHP run 41238247509.
- Graphify run 41238247478.
- Database matrix run 41238247686, covering MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.

Coverage includes clean migrations 000–018, schema comparator, disposable backup/restore/schema/row-count checks, content and media recovery integration, logging/redaction, upload policy, public routes, and Graphify. The Apache `daemon` log ACL was separately verified locally.

Still pending: locate and verify the owner's full database backup, restore it only to a new isolated database, and separately locate/sample-restore private media. These are not proven by CI. See [current state](docs/CURRENT_STATE.md), [backup/restore runbook](docs/DATABASE_BACKUP_RESTORE.md), and [completion plan](docs/COMPLETION_PLAN.md).

## 10. Phase 2 execution handoff — 2026-10-31 (historical, merged)

Phase 2's automated security baseline is merged into `main` through PR #18. Exact tested head `c23dde25f2bee53068c85e775ca56fa93f52e0a9` passed:
- PHP syntax/security run 41238382181.
- Graphify run 41238382162.
- Database integration run 41238382193, including migration 019 on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.

Included changes: generic login errors, account `auth_version` session invalidation, password-reset/login race protection, CSRF policy for all 49 POST routes, public POST allow-list, admin updater route hardening, focused object-level issue/reply/diagnostic ownership tests, private-help participant/attachment tests, and Graphify/API route documentation.

Remaining manual review before production:
- exhaustive object-level authorization and state-transition review across all POST routes;
- broader SQL/input-validation and output-encoding review;
- remaining upload/private-file path and symlink edge cases;
- storage-recovery reconciliation and notification retry behavior;
- host updater/sudoers review (do not run the updater on the dirty local checkout).

Account anonymization has no approved CLI entry point or cron worker. The 30-day deadline does not mean automatic completion; the removed CLI must not be reintroduced or scheduled without separate review and explicit owner approval.

Phase 3 remains unstarted: complete browser/mobile/PWA acceptance, cross-device journeys, and final readiness report before claiming production readiness.


## 2026-11-01 — Administrator terminal boundary

- Branch `feat/admin-terminal-batch-safe` adds bounded multi-line diagnostics to the browser console: at most 8 allowlisted commands, 300 characters per command, 1,800 characters per batch, and 10 batches/minute. Each command is dispatched internally without a shell interpreter.
- Browser terminal does not provide arbitrary command execution, file mutation, package installation, `sudo`, or `su`. Never send a sudo password or SSH credential through the website.
- Use direct SSH from a trusted Tailscale-connected client for privileged host operations; sudo prompts remain inside the SSH TTY. No host SSH/Tailscale/sudoers settings were changed by the repository work.
- Focused policy test added at `tests/php/admin-terminal-policy.php`. Exact implementation commit `bf6950a395adc33849f5cff2237050e7246d9bcf` passed PHP syntax/policy tests, Graphify, and the MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11 disposable integration matrix. Browser/mobile runtime and host SSH/Tailscale configuration remain unverified.
