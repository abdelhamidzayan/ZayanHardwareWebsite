# Zayan Hardware — Update Log

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
