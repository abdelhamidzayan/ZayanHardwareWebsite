## 2026-10-31 — Single-file fresh database schema and GitHub cleanup

- **Type:** Database / CI / Repository hygiene / Documentation
- **Fresh install:** Added `database/fresh_install.sql`, a single SQL entry point consolidating the complete schema represented by migrations 000–019. The duplicate terms-table migration is omitted; first-admin auto-promotion is removed from the new-install path and remains an explicit guarded owner CLI operation after registration.
- **CI:** Updated the database integration workflow to execute the consolidated SQL file on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11, then run the existing schema-object, foreign-key, retention-trigger, bootstrap, and feature integration checks. Exact-head results are pending.
- **Documentation:** Updated `database/FRESH_INSTALL.md`, README, current state, and completion plan. The numbered migrations remain as historical incremental migration records and must not be replayed on top of the new consolidated schema.
- **GitHub hygiene:** Confirmed zero open PRs and zero open issues. Added a one-time path-scoped Actions workflow targeting only the 26 reviewed superseded branch refs; it preserves `main`. Branch deletion is pending workflow completion and live-ref verification.
- **Safety:** No live database, media tree, or deployment was changed. The consolidated SQL is for a newly created empty database only.
- **Status:** Implemented on `main`; branch cleanup completed and live verification shows only `main`. The complete single-file fresh-install and integration matrix passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11 (run [41240801466](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41240801466)). PHP and Graphify also passed on the same exact `main` head.
  
# Zayan Hardware — Update Log

## 2026-10-31 — Skill-test input and answer-exposure hardening

- **Type:** Security / Tests / CI
- **Summary:** Recovered the unmerged skill-test validation work and rebased it cleanly on the consolidated `main` baseline.
- **Implementation:** Admin-authored JSON is capped at 256 KiB and normalized to a whitelist; questions/options/correct-answer keys are bounded and cross-validated. Public skill-test DTOs expose only question/options/index. Stored question structures are validated before use; inactive skill domains cannot display or accept attempts. Unknown, oversized, and non-string answers are normalized to empty before scoring and persistence.
- **Tests:** Added `tests/php/skill-test-input-integration.php` to the disposable MySQL/MariaDB integration workflow. It checks answer redaction, malformed question lists, oversized unknown answers, bounded attempt history, and inactive-domain rejection.
- **Merge:** PR #19 merged into `main` as `bf871bba8de078588a0d17778ed4520199188f96`.
- **Verification:** Exact head `d58282608187d5cbd5af59c66c332b2a7394b20e`: [database matrix 41239306049](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306049), [PHP syntax 41239306080](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306080), and [Graphify 41239306068](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306068) all passed.
- **Safety:** No migration, owner database, existing attempt, or skill rating was changed. Test fixtures run only in `zayan_fresh_install_test`.
- **Status:** Merged and CI-verified; broader application-wide input/output security review remains open.


## 2026-10-31 — Consolidate the development baseline on main

- **Type:** Integration / Security / Database / Documentation
- **Summary:** Merged the accumulated product feature stack plus Phase 1 reliability and Phase 2 session/CSRF security baselines into the single authoritative `main` branch.
- **Code areas:** Profiles/avatars, personal help and private attachments, drafts/audio/image composer, device catalog, technical content preservation, migrations 000–019, session invalidation, admin updater hardening, Graphify, CI workflows, tests, and release documentation.
- **Database:** Migration 019 is included and CI-tested on disposable MySQL/MariaDB databases. It has not been applied to the owner's local database.
- **Safety:** PR #17 merged as `94ad4e077de6d0698ca1f2dad138a9fa9ada62cf`; PR #18 merged as `dadddde53f394a0fe353b575ec13dbeb85749f6e`. No live database, private-media tree, or hosted deployment was modified. The account-anonymization CLI remains removed; no worker or cron is approved.
- **Verification:** Phase 1 head `4bf013f81fc71bc32f8b221fefc1c778d00419d8`: [PHP 41238247509](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247509), [Graphify 41238247478](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247478), [database matrix 41238247686](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247686) all passed. Phase 2 head `c23dde25f2bee53068c85e775ca56fa93f52e0a9`: [PHP 41238382181](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382181), [Graphify 41238382162](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382162), [database matrix 41238382193](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382193) all passed.
- **Remaining gates:** Owner database backup restore, private-media sample restore, exhaustive manual authorization/SQL/XSS/upload/recovery review, browser/mobile/PWA acceptance, and deployment are not complete. The local Kali worktree remains dirty and must be reconciled without discarding its files.
- **Status:** Unified development baseline merged into `main`; not deployed and not yet declared production-ready.



## 2026-10-31 — Phase 1 CI reverified after authorization regression additions

- **Type:** Tests / Security / Database / Documentation
- **Summary:** Re-ran the complete Phase 1 workflow set on the latest branch head after adding object-level authorization regression coverage for technical posts, replies, diagnostic updates, and private-help participants. All current-head workflows completed successfully.
- **Code areas:** `tests/php/issue-authorization-integration.php`; `tests/php/help-request-media-integration.php`; existing Phase 1 PHP, logging, database migration, backup/restore, and Graphify workflows.
- **Database:** No owner database was changed. Fresh-install migrations 000–018 and disposable backup → gzip/checksum → restore → schema-object/row-count comparison passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.
- **Safety:** CI used disposable databases and temporary storage only. It did not restore or modify the owner's database, private media, or deployment.
- **Verification:** Current Phase 1 head `c35c71f39d0702178d8dc9aad041d247b29c29b1`: [PHP workflow 41238036009](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238036009) passed; [Graphify workflow 41238036104](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238036104) passed; [database matrix 41238036402](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238036402) passed all three engines. Graphify scanned 151 files, found 72 routes and 698 nodes/1,637 edges; 5 dynamic JavaScript API call sites remain unresolved by static matching and are documented for manual review.
- **Remaining blockers:** Owner-machine database restore, separate private-media backup/sample restore, real Apache `daemon` log-write permissions, and browser/PWA runtime checks are not proven by CI.
- **Documentation:** [Current state](docs/CURRENT_STATE.md) · [Completion plan](docs/COMPLETION_PLAN.md) · [Graphify guide](docs/graphify.md) · [AI handoff](AI_CONTEXT.md).
- **Status:** Phase 1 automated CI gate passed on the current head; Phase 1 remains open pending owner-machine acceptance. PR #17 remains a draft targeting `main`; no merge or deployment is claimed.

## 2026-10-31 — Phase 2 exact-head CI passed; local Apache/updater checks recorded

- **Type:** Verification / Security / Infrastructure / Documentation
- **Summary:** Verified the three Phase 2 workflows on exact commit `889dd99c74824674789a138bfc7dd3b9dd30444e`; recorded local Apache log-directory and updater-policy findings.
- **Code areas:** Documentation only in this checkpoint; no application code or local machine files changed.
- **Database:** No migration applied to the owner's local database. Migration 019 remains part of the branch's tested fresh-install/integration workflow only.
- **Safety:** All three exact-head workflows passed: [PHP syntax/security](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933680), [Graphify](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933760), and [database migration integration](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933686). Locally, Apache runs as `daemon`, but `storage/logs` is `root:root 0775` and daemon cannot write to the directory; existing log files are `daemon:daemon 0660`. The daemon sudoers rule is limited to the fixed updater script as user `a`. The script must not be run against the current dirty non-main checkout.
- **Verification:** Exact-head CI passed. Local log-directory write failure and updater policy were verified by owner-run read-only commands. No permissions, sudoers, database, files, or local Git state were changed.
- **Documentation:** [Current state](docs/CURRENT_STATE.md) · [Completion plan](docs/COMPLETION_PLAN.md) · [POST route security matrix](docs/SECURITY_POST_ROUTE_MATRIX.md).
- **Status:** Phase 2 CI-verified at the recorded commit; Phase 2 security review and local acceptance remain open.

---

## 2026-10-31 — Phase 2 starts: session revocation and POST CSRF matrix

- **Type:** Security / Authentication / Tests / Documentation / Database
- **Summary:** Hardened login information disclosure, made account/password/role lifecycle transitions revoke previous sessions, closed the in-flight stale-password rehash race, protected the admin updater, and added automated CSRF/authorization regression gates.
- **Code areas:** `AuthService`, `AuthController`, `User`, `AdminUserController`, `AccountController`, `AccountRetentionService`, `AdminUpdateController`, `RateLimiter`, migration `019_session_version_invalidation.sql`, session/login/account-policy/admin-update/POST-CSRF tests, Graphify JavaScript route map, and PHP/database workflows.
- **Database:** Migration 019 adds `users.auth_version` (default 1). It is included in the fresh-install sequence and disposable database workflow. It has **not** been applied to the owner's local database.
- **Safety:** Public login errors do not disclose suspended-account status. Password reset, suspension/ban/reactivation, role change, deletion request, and identity anonymization increment the auth version. Old sessions remain invalid after reactivation; an in-flight old-password login cannot overwrite a newer password hash. Existing sessions without the version must sign in again after the migration is deployed.
- **Verification:** Earlier Phase 2 CI runs passed the PHP/Graphify/database baseline through migration 019, but later commits added admin-update hardening, generic login error checks, and account state/role session invalidation. The latest exact-head workflows are pending. Do not mark the newest changes tested until all current-head jobs pass.
- **Documentation:** [Completion plan](docs/COMPLETION_PLAN.md) · [Current state](docs/CURRENT_STATE.md) · [POST route security matrix](docs/SECURITY_POST_ROUTE_MATRIX.md) · [Security hardening](docs/security-hardening.md) · [JavaScript route map](docs/javascript-api-route-map.md).
- **Status:** Implemented on `phase2/security-graphify-20261031`; tests pending; not merged to `main` or deployed.

---

## 2026-10-31 — Phase 1 CI gate passed on MySQL/MariaDB matrix

- **Type:** Tests / Database / Logging / Infrastructure
- **Summary:** Completed the first full Phase 1 automated verification after the repository became public. Added an isolated real-HTTP access-log regression test; corrected the standalone Bearer redaction boundary after its test exposed a regex match against an event label.
- **Code areas:** `tests/php/logging-http-access.php`; `tests/php/logging-exception-redaction.php`; `app/core/App.php`; `.github/workflows/php-lint.yml`; `bin/compare-database-schema.php`; `.github/workflows/database-migrations.yml`.
- **Database:** No migration applied to the owner's database. Fresh-install migrations 000–018 and disposable backup/restore/metadata/row-count comparison passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.
- **Safety:** CI used disposable synthetic databases and temporary storage roots. No live database, private-media tree, or deployment was modified. Technical-content retention, media recovery, and account-anonymization integration tests passed in the database matrix.
- **Verification:** Commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab`: PHP workflow [41219585375](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41219585375) passed; Graphify workflow [41219585292](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41219585292) passed; database matrix [41219585295](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41219585295) passed all three engines and all steps. The local owner database restore, separate private-media archive/sample restore, and actual Apache `daemon` permissions remain unverified.
- **Documentation:** [Completion plan](docs/COMPLETION_PLAN.md) · [Current state](docs/CURRENT_STATE.md) · [Backup/restore runbook](docs/DATABASE_BACKUP_RESTORE.md) · [Storage recovery triage](docs/STORAGE_RECOVERY_RUNBOOK.md).
- **Status:** Phase 1 automated CI gate passed; owner-machine acceptance remains pending. PR #17 remains a draft; nothing is merged to `main` or deployed.

---

## 2026-10-31 — Phase 1: backup/restore gate, schema comparator, and logging hardening

- **Type:** Database / Infrastructure / Security / Tests / Documentation
- **Summary:** Added a read-only schema/row-count comparison CLI, expanded log redaction for Authorization/Cookie headers, bearer/query tokens, and session IDs, plus retention coverage, added a disposable backup/restore/checksum/compare step to the three-engine migration workflow, and documented safe triage for pending storage recovery tasks.
- **Code areas:** `bin/compare-database-schema.php`; `app/core/App.php`; `tests/php/logging-exception-redaction.php`; `.github/workflows/database-migrations.yml`.
- **Database:** No migration added or applied by this checkpoint. The GitHub workflow creates a new disposable restore-test schema for each CI job; it does not target the owner's database.
- **Safety:** Comparator uses read-only information_schema queries and SELECT row counts. Restore flow creates a new target and intentionally does not overwrite an existing database. Raw Authorization/Cookie headers, bearer values, access/refresh/id/general token values are redacted before log output.
- **Verification:** GitHub source files and workflow configuration were fetched and reviewed. PHP lint, isolated log test, database restore matrix, and runtime checks have **not yet been executed** on this branch. The previous PR #16 CI runs executed zero steps due to exhausted Actions minutes; recheck usage before triggering new runs.
- **Documentation:** [Completion plan](docs/COMPLETION_PLAN.md) · [Current state](docs/CURRENT_STATE.md) · [Backup/restore runbook](docs/DATABASE_BACKUP_RESTORE.md) · [Storage recovery triage](docs/STORAGE_RECOVERY_RUNBOOK.md) · [README](README.md).
- **Status:** Implemented on `phase1/foundation-reliability-20261031`; not yet test-verified, merged, or deployed.

---

## 2026-10-31 — Start the three-phase completion program

- **Type:** Project planning / Infrastructure
- **Summary:** Established three controlled completion phases: (1) database, data integrity, and operational reliability; (2) security, account lifecycle, and Graphify/dependency integrity; (3) UI, PWA, cross-device acceptance, and final readiness. Phase 1 is now active.
- **Code areas:** Documentation and verification workflow only at this checkpoint.
- **Database:** No migration was applied and no live database was modified.
- **Safety:** PR #16 was confirmed merged into `audit/content-retention-consistency` at `098442813eb5a55e96d5fff27ce127f9b5f08591`; this did not merge to `main` or deploy. Phase 1 is isolated on `phase1/foundation-reliability-20261031`.
- **Verification:** GitHub API confirmed PR #16 merged and the current workflow configuration. The latest recorded CI runs on the merged PR head executed zero steps because the included Actions minutes were exhausted. No current-head application test is claimed as passing.
- **Documentation:** [Three-phase completion plan](docs/COMPLETION_PLAN.md) · [Current state](docs/CURRENT_STATE.md).
- **Status:** Plan documented; Phase 1 in progress. Not a release or deployment.

---

This is the chronological record of meaningful project changes. New entries go **at the top**, newest first.

## Rules for keeping this log useful

- Record completed features, behavior changes, important bug fixes, schema changes, security changes, and meaningful infrastructure changes.
- Keep entries factual and concise. Do not list every command, temporary experiment, or routine formatting change.
- Mention the affected code areas and link the relevant feature/design/migration document.
- State database impact explicitly: none, migration added, migration applied to a named environment, or not verified. A migration file existing does not mean it has been applied.
- Record tests by their actual result. Separate implemented, locally tested, CI-tested, runtime-verified, and deployed.
- Never describe a draft PR as merged, a branch as deployed, or an unrun test as passing.
- Do not include secrets, credentials, private member data, raw logs, or backup contents.

## Entry format

Use this format for future updates:

### YYYY-MM-DD — Feature or change title

- **Type:** Feature / Fix / Security / Database / UI / Infrastructure / Documentation
- **Summary:** What changed and why.
- **Code areas:** Main files, services, controllers, models, routes, views, or assets affected.
- **Database:** Migration/table/index/FK impact, or `None`.
- **Safety:** Authorization, privacy, content-retention, upload, or other relevant considerations.
- **Verification:** Exact tests/checks run and their results. Clearly list anything not run.
- **Documentation:** Links to updated feature, architecture, or setup documents.
- **Status:** Implemented / Tested / Runtime-verified / CI-verified / Deployed — only the states supported by evidence.

---

## 2026-10-31 — Establish AI handoff and feature documentation workflow

- **Type:** Documentation
- **Summary:** Added a consistent handoff path so future developers and AI assistants can understand the existing project before changing it. Established this update log and a concise current-state page, plus a reusable AI start prompt.
- **Code areas:** Documentation only. No PHP, JavaScript, CSS, SQL, migration, or runtime behavior changed in this entry.
- **Database:** None.
- **Safety:** Existing data-preservation, branch-safety, and contributor rules remain authoritative in `AGENTS.md`.
- **Verification:** Documentation links and paths were checked against the repository tree on this branch. No application tests were required because application code was not changed.
- **Documentation:** [AI context](AI_CONTEXT.md) · [Current state](docs/CURRENT_STATE.md) · [AI start prompt](docs/AI_START_PROMPT.md) · [Architecture map](docs/architecture-code-map.md).
- **Status:** Documentation files added/updated on branch `docs/ai-handoff-and-feature-log-20261031`. This is not a merge to `main`, not a deployment, and not an application-code release.

- 2026-10-31: Verified Apache log directory ACL locally. Removed `other` access from the log directory access/default ACL and `permission-test.log`; two create-file tests as `daemon` succeeded and new files inherited `other::---`. No ownership or broad mode changes.


## 2026-10-31 — Object-level authorization regression expansion

- Extended `tests/php/issue-authorization-integration.php` to reject cross-user edits and soft-deletes of technical posts, edits/deletes of another author's replies, unauthorized diagnostic creation/edit/delete, and accepting a solution on another member's help request. Existing moderator/admin result-review and self-vote protections remain covered.
- Extended `tests/php/help-request-media-integration.php` to verify requester/helper access to private request and attachment metadata, reject a nonparticipant, and reject a nonparticipant attachment write.
- These are focused model/service integration cases, not a claim that every POST route has exhaustive end-to-end authorization coverage. The remaining route-by-route authorization review stays open until all handlers and state transitions are reconciled.
