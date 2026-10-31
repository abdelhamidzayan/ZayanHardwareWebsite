# Zayan Hardware — System Rebuild Audit

**Audit date:** 2026-10-31  
**Working branch:** `audit/system-rebuild-20261031`  
**Reference note:** SHA values and workflow results below are dated snapshots; query the live PR head before starting another change.  
**PR:** [#16 — Audit rebuild: fresh install, retention guards and route consistency](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/16)  
**PR state:** Draft / open / unmerged; base `audit/content-retention-consistency`  
**Status:** Foundational protection and several new content-write changes are implemented. The latest code is not yet CI-verified. This document is not a final sign-off.

## 1. Owner direction and data boundary

The owner has confirmed that legacy/demo content is not a blocker and that the database will be renewed. This permits a deliberate fresh-install plan. No database has been dropped, truncated, reset, or otherwise modified by this audit. GitHub CI uses disposable synthetic databases only. The owner's Kali/XAMPP database, backup files, and private storage have not been accessed or modified by this review.

The live product must protect technical knowledge after launch. Technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, approved help knowledge, attribution, and consent/audit records must not be physically deleted by ordinary application paths.

## 2. Exact GitHub and CI state

- Default branch `main` was last observed at `8732fd70e3a85ecfb2c8e94c69fd7f4a6ebfa932`.
- PR #16 is a child of `audit/content-retention-consistency`; it is intentionally not based directly on `main`.
- PR #16 remains Draft/open/unmerged. No PR was merged, retargeted, or deployed during this audit.
- Always query the live PR head before making changes; the SHA above is a dated snapshot, not a permanent branch pointer.

### Last confirmed green baseline

The latest successful set inspected before the newest content-write changes was commit `55522a3cc204e961aaf9947773b15ff10321ad02`:

| Workflow | Run | Result |
|---|---:|---|
| PHP syntax, CLI boot, RateLimiter API, draft rollback, public-route policy, exception redaction, JavaScript syntax | [41180453075](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453075) | Success |
| Graphify tests, graph generation, deterministic check, artifact upload | [41180453042](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453042) | Success |
| Fresh install and database integration | [41180453039](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41180453039) | MySQL 8.0, MariaDB 10.4.32, MariaDB 10.11 all successful |

The inspected DB logs confirmed:
- The full `000 → 017` sequence completed on all three engines.
- 73 foreign-key references were present in that verified schema.
- First-admin bootstrap promoted the synthetic owner account and rejected a second bootstrap.
- Standalone technical post, reply, attachment, dependent post, and user physical-delete attempts were rejected.
- Reply tombstone integration preserved the parent-child relationship after soft-deleting the parent.

**This green baseline does not verify migration 018 or the latest ContentWriteService and failure-injection additions.** The current workflow file contains migration 018 and the content-write integration step, but those are not considered passed until a successful run executes them on the current head.

### Latest attempts and Actions quota

On the latest observed head `c015a8d751c17e1ffa3349786324eaf08e38609d`, GitHub reported these runs as failed:

| Workflow | Run | Job execution |
|---|---:|---|
| PHP syntax check | 41184388274 | Job failed with `steps: null` |
| Graphify dependency graph | 41184388393 | Job failed with `steps: null` |
| Database migration integration | 41184388353 | All three DB matrix jobs failed with `steps: null` |

No workflow steps executed in these runs. These statuses are **not** evidence of a PHP, Graphify, SQL, or migration test failure, and they are not successful verification either.

The owner supplied a GitHub notice showing **2,000 / 2,000 included Actions minutes used**, with usage scheduled to reset on **2026-11-01**. The owner has not confirmed that an Actions spending budget of **$0** is configured. Repeated branch commits were also triggering duplicate `push` and `pull_request` runs for the same PR update. The three workflows now use `push` on `main` plus `pull_request` for branch review, and each has a per-PR/ref concurrency group with `cancel-in-progress: true`; this reduces duplicate and stale work without disabling any workflow or removing its tests. Follow [the mandatory GitHub Actions and usage-budget policy](../AGENTS.md#github-actions-and-usage-budget):
- keep workflow YAML files enabled and version-controlled;
- do not repeatedly rerun or dispatch jobs while quota is exhausted;
- confirm the account's $0 spending budget applies to Actions before allowing overage;
- resume the full relevant CI checks after the displayed reset date or explicit owner authorization;
- do not report the latest code as CI-passing until those exact tests pass.

## 3. Verified foundational implementation

### Routing and access policy

- Guest access is declared on individual route registrations through `getPublic()` / `postPublic()`; ordinary `get()` / `post()` routes remain private.
- Public routes cover the documented landing/help/policies, authentication, member-profile, avatar, and consent-approved help-library surfaces.
- Feed/discussion pages, drafts, private attachments, inboxes/messages, account actions, and administration remain authenticated.
- The avatar route validates the raw positive integer before using it as a database identifier.
- `tests/php/router-public-routes.php` checks public/private route behavior and the route parameter contract.
- POST authorization review follows delegated controller helpers as well as individual route handlers. For example, HelpRequestController::accept()/decline() delegate to respond(), which performs CSRF validation, session authentication, participant lookup and a pending-state mutation before sending notifications. A method-name scan alone can therefore misclassify delegated checks.

### Fresh database and first administrator

- `000_core_identity_schema.sql` supplies the missing baseline `users` and `technician_profiles` tables for a genuinely empty database.
- `database/FRESH_INSTALL.md` and `.github/workflows/database-migrations.yml` specify the ordered sequence from migration 000 through migration 018.
- The two `002_` migrations are sequential historical files, not alternatives. Never infer the applied state from filenames or replay them blindly on an existing database.
- `bin/bootstrap-first-admin.php` is CLI-only, requires exact database confirmation and an existing active owner account, uses an advisory lock, refuses a second bootstrap when an administrator exists, and writes an audit record.
- The owner's already-applied local migrations 014/015 must not be replayed against that local database.

### Technical-history retention

- Migration 016 changes 43 content/attribution foreign keys to `ON DELETE RESTRICT`.
- Migration 017 contains 28 `BEFORE DELETE` triggers protecting technical, identity, personal-help, consent, skill-evidence, catalog, and audit records, including standalone posts and replies.
- Migration 018 adds the `storage_recovery_tasks` table and a further no-physical-delete trigger. It records path/hash/reason/status metadata only; it does not execute file operations or contain a cleanup worker.
- The migrations add constraints, triggers, and the recovery table; they do not delete, truncate, or rewrite existing technical content.
- The original successful CI baseline executed migrations 000–017. Migration 018 is included in the current workflow but awaits a new successful run.

### Reply-tree retention and logging

- `Issue::deleteReply()` preserves `parent_reply_id`. If active child replies remain, the deleted parent is returned as a tombstone with its text hidden.
- Reply tombstone integration passed on the last successful fresh-install matrix and also checks physical-delete rejection.
- `App::exceptionContext()` / `App::logException()` centralize safe exception logging. Arbitrary exception messages are not copied; PDO details are reduced to safe SQLSTATE/driver metadata.
- `tests/php/logging-exception-redaction.php` checks that synthetic bearer tokens, URL tokens, and SQL secrets do not leak into logs. It passed in the earlier green CI baseline.

## 4. Implemented after the last green baseline — awaiting CI

### Atomic technical-content writes

- `app/services/ContentWriteService.php` now coordinates issue creation/editing, reply creation/editing, and diagnostic-update creation/editing.
- The service owns the outer PDO transaction; the involved `Issue` model methods are transaction-aware.
- Uploaded files are moved to private issue storage before commit and tracked immediately. If a later DB/file step fails, the service rolls back and compensates moved files in reverse order.
- Confirmed rollback permits removal of newly moved files. Cleanup failures are written to `storage_recovery_tasks`.
- If the commit response is ambiguous, moved files are preserved and a pending recovery task is recorded rather than risking deletion of a file referenced by committed DB rows.
- A successful explicit rollback after a commit exception is now treated as confirmed rollback. The service injects the commit operation for deterministic failure testing.
- `tests/php/content-write-integration.php` covers successful attachment writes, rollback after attachment failure, move adapters that report false or throw after moving (the service tracks and compensates the destination), later file-move failure, duplicate tokens, reply/diagnostic failure, edit-history rollback, failed compensation, confirmed rollback after commit exception, and a commit that succeeds while its acknowledgement is lost.
- The database workflow is configured to run this test against the disposable fresh-install schema on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11. This test has **not** passed on the current head yet.

### Administrative mutations and device catalog

- AdminRosterService::assertActorActive() revalidates an administrator's role and active account status while locking the user row. The user/session role cached earlier in the request is not treated as sufficient for a sensitive write.
- Skill-test creation and activation/deactivation now revalidate the administrator inside the same transaction as the test write and its security-audit event. Test activation locks the target test row and does not create duplicate audit events for no-op status submissions. Test creation locks the active skill domain before inserting the draft test.
- Device-model moderation now enforces active-admin authorization in the model itself, locks the suggestion and manufacturer rows, prevents approval under an inactive manufacturer, and commits the catalog change plus the audit event atomically. Rejection remains possible for an inactive manufacturer's pending suggestion.
- Featured-home-post changes now revalidate the active administrator inside a transaction, lock the target non-deleted post, and write a security-audit event atomically when the selection changes.
- Device model search now caps query length at 80 characters, rate-limits authenticated searches, and returns models only when both model and manufacturer are active.
- tests/php/device-catalog-integration.php verifies model-level rejection of a non-admin, active-admin approval, submitter/approver attribution, audit recording, overlong-query rejection, and search visibility. The database workflow includes this test, but the current head remains CI-unverified.
- These changes improve the reviewed administration paths; they do not mean the full POST authorization matrix is complete. Other POST routes still require method-by-method verification and failure tests.

### Shared image upload limits

- `ImageUploadPolicy` enforces an 8,000-pixel maximum dimension and a 20-million-pixel ceiling to limit compressed-image decompression risk.
- The shared check is used in the reviewed issue/content, personal-help, avatar, draft, and ContentWriteService paths; per-route byte-size and MIME validation remain required.
- `tests/php/upload-policy.php` exercises valid images, dimension/pixel ceilings, malformed images, malformed upload shapes, and mismatched PHP upload indexes. Treat the latest version as awaiting current-head CI.

## 5. Remaining gaps

1. **Draft media lifecycle:** publishing has a transaction-aware file-move/restore path, including ambiguous-commit recovery. Draft upload, rendered-image replacement, editor-state changes, per-item deletion, and draft deletion use revision locking and reference checks/compensation. Cleanup now records durable pending tasks when the storage root or draft UUID directory cannot be resolved, rejects UUID-directory symlinks that point at another location, and refuses dot-only filenames. `tests/php/draft-media-recovery-integration.php` covers unavailable roots/directories and cross-draft symlinks. A broader end-to-end browser journey for full draft CRUD remains.
2. **Personal-help media semantics:** request + initial message, and each sent message, are committed separately from media. Each attachment then gets its own DB transaction, file compensation, and recovery-task recording. This intentionally permits a message to exist when one or more attachments fail; the controller tells the user to open the conversation and review what was saved. It is not an all-or-nothing transaction across the message and every attachment. Current-head integration and the partial-save UI journey still need CI/runtime verification.
3. **Recovery operations:** migration 018 records pending tasks but no background worker or automatic file deletion exists. A future reconciliation command must inspect both the database references and file state and must never remove a referenced technical file.
4. **POST authorization matrix:** complete every POST route across authentication, role, owner/participant, state transitions, CSRF, rate limits, validation, and model-level invariants.
5. **Dynamic JavaScript API references:** five sites were unresolved in the last successful Graphify snapshot and require manual view/script tracing.
6. **Browser/PWA:** automated registration/login, composer, mobile layout, offline transitions, Safari installation, push delivery, and private attachment journeys remain unverified.
7. **Owner backup and live schema:** the latest owner backup has not been restored again during this audit, and the current local database's schema/applied migration history has not been re-introspected here. Do not apply migrations 016–018 locally until the actual schema/history, backup, and rollback plan are reviewed.
8. **Runtime/storage:** no fresh Kali/XAMPP/Apache permission test or live HTTP/PWA verification was executed in this pass.
9. **Notification delivery guarantee:** `NotificationService` treats notification creation and Web Push as best-effort side effects and catches/logs delivery failures, so a committed content write is not reported as a failed write merely because notification delivery failed. There is no durable outbox/retry guarantee yet; transient delivery failures can still mean a notification is missed. This is a delivery-reliability enhancement, not a content-transaction blocker.

## 6. Verification ledger

| Area | State |
|---|---|
| PR #16 ancestry/state and current branch reference | Verified through GitHub API; Draft/open/unmerged |
| Guest/public route policy regression | Passed in prior CI baseline |
| PHP syntax, CLI boot, RateLimiter API, previous draft rollback, JS syntax | Passed on commit 55522a3; latest head not verified |
| Exception-log secret redaction | Passed on commit 55522a3 |
| Graphify unit tests/generation/determinism | Passed on commit 55522a3; latest head not verified |
| Fresh install 000–017 on MySQL 8.0 / MariaDB 10.4.32 / 10.11 | Passed on commit 55522a3 |
| Migration 018 and latest fresh-install 000–018 sequence | Present/configured; awaiting current-head CI |
| First-admin bootstrap and delete guards | Passed on the prior CI baseline through migration 017 |
| Reply tombstone / thread ancestry integration | Passed on the prior CI baseline |
| Admin skill-test create/status writes and audit events | Implemented with active-admin row locking; current-head CI pending |
| Device-model moderation authorization, duplicate guard, attribution and audit | Implemented with model-level integration test; current-head CI pending |
| Featured-home-post mutations and audit events | Implemented with active-admin revalidation and row lock; controller-level integration coverage still needed |
| Device model search length/rate/active-catalog constraints | Implemented; current-head CI pending |
| ContentWriteService create/edit and filesystem compensation | Implemented; false-return and throw-after-move failure injection added; current-head CI pending |
| Confirmed-rollback and ambiguous-commit failure injection | Implemented in test; current-head CI pending |
| Shared image dimension/pixel policy | Implemented; current-head CI pending |
| Draft publication file recovery | Implemented with failure-injection tests; current-head CI pending |
| Draft media cleanup root/directory recovery and UUID-symlink containment | Implemented with isolated integration test; current-head CI pending |
| Draft media CRUD cleanup | Revision-locked operations, durable unavailable-root/directory tasks, UUID-symlink containment and dot-filename rejection implemented; current-head CI and full browser journey pending |
| Personal-help message/media | Per-attachment transaction and compensation implemented; intentional partial-save semantics; current-head CI/UI verification pending |
| Notification delivery | Best-effort isolation reviewed; no durable outbox/retry |
| Full POST authorization matrix | Partial review only |
| Dynamic JavaScript API references | Five sites remain for manual review |
| Browser/mobile Safari/PWA journeys | Not tested |
| Owner backup restore and current local schema comparison | Not performed in this pass |
| Production deployment or PR merge | Not performed |

Do not describe the full system as complete until the remaining gaps are implemented or explicitly accepted, the current-head CI matrix passes, and the required local/browser/data checks have been separately executed.
