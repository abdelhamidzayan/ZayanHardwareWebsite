## 2026-11-01 — Bounded admin terminal batches

- New branch: `feat/admin-terminal-batch-safe`, based on the live `main` head inspected for this task.
- The browser diagnostic console accepts at most 8 separate allowlisted commands per batch (300 characters each, 1,800 total), limits usage to 10 batches per minute, bounds combined output, and retains CSRF, active-admin revalidation, audit logging, and existing path/secret protections.
- No shell interpreter or arbitrary command execution is introduced. `sudo` and `su` are rejected by the web endpoint.
- Privileged operations are documented for direct SSH TTY sessions over Tailscale; sudo passwords never pass through the PHP app. Host SSH, Tailscale ACL, firewall, account, and sudoers configuration are unchanged.
- Added a focused source-policy test and wired it into the PHP workflow. CI and local PHP/browser runtime checks remain pending; no deployment or merge is claimed.

## 2026-11-01 — Brand identity implementation and self-review

- Created isolated branch `brand/cream-green-identity` from the authoritative `main`.
- Applied the cream/ivory + workshop-green palette to shared tokens, CTAs, public/onboarding screens, post composer, issue discussion, PWA guide, browser/PWA theme metadata, and launcher artwork.
- Added `docs/BRAND_IDENTITY.md` as the canonical palette and usage guide.
- Self-review added accessible foreground variants for orange actions, higher-contrast muted text, and centralized semantic status colors. All eight layout-loaded CSS files were checked for balanced braces and the targeted legacy palette values; this is source-level verification only.
- No database, routes, technical content, or private media were changed.
- PHP/JS tests, CI, browser/mobile runtime, merge, and deployment remain pending. The branch must not be treated as the live `main` or local XAMPP state.

## 2026-11-01 — Migration readiness preflight

- PR #38 merged read-only database diagnostics into main as `fbc78e69534f97f5ace9a370af549eeb940f7064`.
- Follow-up branch `feat/migration-readiness-preflight` adds read-only `db preflight`, which detects duplicate historical sequence prefixes and reports whether the selected database has a migration ledger or an unknown existing baseline.
- Historical migration files are not a safe execution queue: two files share prefix 002, and the consolidated fresh-install SQL is only for an empty database.
- No migration runner or SQL execution has been enabled. Existing-database adoption still requires a verified backup/restore, schema comparison, and explicit baseline approval.
- Verification for the follow-up branch is pending; no owner database or private media was changed.

## 2026-11-01 — Admin database diagnostics feature branch

- Added read-only `db status`, `db tables`, and `db migration <filename.sql>` commands to the admin diagnostic console on `feat/admin-database-migration-diagnostics`.
- Applied migration state is reported as UNKNOWN when no supported ledger exists; no SQL execution or migration application is provided.
- Source review only so far; PHP/CI/browser/runtime checks are pending. No database was modified.
- See [Admin database diagnostics](ADMIN_DATABASE_DIAGNOSTICS.md).

# Zayan Hardware — Current Project State

> **Last updated:** 2026-11-01. Always recheck live GitHub refs, local checkout, database, and deployment before acting.

## 1. Current goal and delivery plan

The owner approved completing the stabilization program, integrating the accumulated feature work, and consolidating development on one authoritative `main` branch.

- **Phase 1 — Foundation and operations:** automated CI passed and the release baseline was merged into `main`. Owner-machine database and private-media acceptance remain pending.
- **Phase 2 — Security and Graphify:** automated security baseline passed and was merged into `main`. The broader manual security review remains open.
- **Phase 3 — UI, PWA, cross-device acceptance:** not started; browser/mobile acceptance and final readiness remain future work.

The full scope and acceptance criteria are in [COMPLETION_PLAN.md](COMPLETION_PLAN.md). A merge into `main` is a development baseline, not a deployment or production-readiness claim.

## 2. GitHub and release state

- PR [#17](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/17) merged into `main` on 2026-10-31. Merge commit: `94ad4e077de6d0698ca1f2dad138a9fa9ada62cf`.
- PR [#18](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/18) merged into `main` on 2026-10-31. Merge commit: `dadddde53f394a0fe353b575ec13dbeb85749f6e`.
- PR [#19](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/19) merged into `main` on 2026-10-31. Merge commit: `bf871bba8de078588a0d17778ed4520199188f96` (skill-test input and answer-exposure hardening).
- Superseded PRs #13, #14, and #15 were closed after reviewing their changes; a live GitHub search currently reports no open pull requests or open issues. A fresh branch search on 2026-11-01 returned 26 branch refs, including `main` and `brand/cream-green-identity`; therefore, the earlier branch-cleanup completion statement is not currently accurate. Do not delete historical refs without reviewing their contents and confirming the intended cleanup scope.
- **Authoritative development branch:** `main`. The Phase 2 code-integration merge commit is `dadddde53f394a0fe353b575ec13dbeb85749f6e`; subsequent commits on `main` are documentation updates. Current exact-head CI on `53d24bb038b656c05d7c0aaea4fb460b1cb15ed5`: PHP [41240801476](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41240801476), Graphify [41240801444](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41240801444), and database matrix [41240801466](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41240801466) all passed. Resolve the live `main` head before new work.
- The merged work includes the accumulated product features, Phase 1 reliability work, and the Phase 2 session/CSRF/object-authorization baseline. It has not been deployed.
- `main` remains the authoritative development branch. The live branch search currently shows 26 refs in total; `brand/cream-green-identity` is the isolated brand-work branch, while the other refs require individual review before any cleanup. Future feature work should branch from the latest `main`.
- The owner's local Kali checkout remains on `local/audit-update-20261031` at `46960cf9efb33b2b0affa503d5e832725bdba4b4`, with modified and untracked files. It is not synchronized with `main`. Preserve those files; do not run the updater, reset, clean, or pull until a safe local reconciliation plan is prepared.
- No merge above is evidence of deployment, live database migration, or production acceptance.

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
| Single-file fresh install | `database/fresh_install.sql` passed the complete fresh-install and integration suite on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11. | CI verified on exact `main` head `53d24bb`; run [41240801466](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41240801466) |
| MySQL/MariaDB matrix | All 3 matrix jobs completed successfully, including the complete integration suite | CI verified |
| Core content write/media recovery | Issue/reply media writes, draft publish outcomes, symlink containment, personal-help media, authorization and reply tombstones passed | CI verified on all 3 engines |
| Public/private route and upload checks | Public/private route policy, image upload policy and PHP/JS syntax suites passed | CI verified |
| Exception log redaction | Authorization/Cookie/Bearer/query/session-ID redaction, four log channels, JSON validity, and retention regression passed | CI verified |
| Access/security/audit channels | Real HTTP request/shutdown logging passed; Apache `daemon` successfully created log files locally and new files inherited `other::---` | CI and local ACL verified |
| Disposable backup/restore comparison | Backup → gzip/checksum → restore into a new schema → compare tables/views/columns/indexes/FKs/checks/triggers/routines/events/row counts passed on all 3 engines | CI verified |
| Owner's full database backup/restore comparison | No full backup archive was found in the project-directory inventory; external backup locations were not inspected | Pending; requires locating a real backup and restoring only to a separate test database |
| Private-media backup and sample restore | No private-media archive was found in the project-directory inventory; external backup locations were not inspected | Pending; requires a separate archive and isolated sample restore |
| Pending storage recovery reconciliation | Durable records exist; a read-only/manual triage runbook is now documented; no general automatic worker exists | Procedure documented; reconciliation pending |
| Read-only schema comparator | CLI check plus full schema-object and row-count comparison during restore passed on all 3 engines | CI verified |
| Phase 1 release-candidate CI verification | PHP, Graphify, and 3-engine database workflows all passed on `4bf013f81fc71bc32f8b221fefc1c778d00419d8` | All CI gates passed; local backup/media acceptance pending |

## 5. Existing technical documentation

- [Architecture and dependency map](architecture-code-map.md)
- [Single-file fresh database installation](../database/FRESH_INSTALL.md) · [SQL schema](../database/fresh_install.sql)
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

- Phase 1 automated verification passed on the release candidate before its merge into `main`; Phase 2 added migration 019 and passed the same MySQL/MariaDB integration matrix.
- CI backup/restore tests use disposable databases. They do not prove that the owner's own backup archive exists or can be restored.
- The project-directory inventory did not identify a full database backup or private-media archive. Their presence in another disk, backup service, or external directory remains unknown.
- No owner database restore, live migration, or private-media restore has been performed as part of this work. Never restore into the source database.
- Apache log directory ACL and inheritance were verified locally: `daemon` can create files and newly created files inherit `other::---`.
- Local Git changes and dated environment notes are recorded in [AI_CONTEXT.md](../AI_CONTEXT.md); recheck before acting.

## 7. Latest Phase 1 implementation checkpoint

The Phase 1 release candidate passed all workflows on head `4bf013f81fc71bc32f8b221fefc1c778d00419d8`:
- [PHP syntax/security workflow](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247509) — passed.
- [Graphify workflow](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247478) — passed.
- [Database integration matrix](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238247686) — passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11.

The gate covered PHP/JS syntax, CLI boot, public-route and CSRF policy, rate limiting, draft rollback, upload policy, HTTP access logging, exception redaction, Graphify determinism, clean migrations 000–018, content/media recovery integration, and disposable backup → gzip/checksum → restore → schema-object/row-count comparison.

Graphify snapshot: 151 files, 72 routes, 168 SQL query fragments, 15 JavaScript API call sites (10 statically linked; 5 dynamic/unresolved), 698 nodes, and 1,637 edges. See [Graphify guide](graphify.md).

Owner database backup restore and private-media sample restore remain pending. The Apache log ACL was subsequently verified locally; no live database or private-media tree was modified.

## 8. Phase 2 — current security work

**Merged development baseline:** PR #18, merge commit `dadddde53f394a0fe353b575ec13dbeb85749f6e`.  
**Exact tested Phase 2 head:** `c23dde25f2bee53068c85e775ca56fa93f52e0a9`.

Implemented and CI-verified:
- Generic login errors for invalid credentials and suspended accounts; blocked-account attempts consume the same IP/identifier rate-limit buckets.
- Migration 019 adds `users.auth_version`; password reset, account status/reactivation, role changes, deletion requests, and final identity anonymization revoke older sessions.
- Login/session version binding and conditional password rehash prevent stale sessions and old-password login races.
- Automated CSRF policy scans all 49 declared POST routes and enforces the public POST allow-list (`/login`, `/register`).
- Admin updater route uses CSRF, per-user rate limiting, active-admin revalidation, fixed command arguments, audit events, and a shared lifecycle lock.
- Object-level regression tests reject cross-user technical-post/reply/diagnostic writes, unauthorized acceptance on another member's help request, and nonparticipant access/writes to private-help requests and attachments.
- Graphify and JavaScript API route documentation were updated.

**Exact-head CI:**
- [PHP syntax/security](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382181) — passed.
- [Graphify](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382162) — passed.
- [Database integration](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238382193) — passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11, including migration 019.

**Additional merged security follow-up:** PR #19 merged into `main` as `bf871bba8de078588a0d17778ed4520199188f96`. Exact tested head `d58282608187d5cbd5af59c66c332b2a7394b20e` passed [database integration](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306049), [PHP syntax](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306080), and [Graphify](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41239306068). Skill-test authoring/input bounds, inactive-domain checks, public answer redaction, and bounded attempt-history persistence are now covered by a disposable-DB integration test.

**Still open:** exhaustive object-level authorization review across every route/state transition; broader application-wide SQL/input-validation and output-encoding review (skill-test-specific validation is implemented); remaining upload/private-file edge cases; storage-recovery reconciliation and notification retry behavior; host-level updater/sudoers review. The CSRF static test is not proof of exhaustive authorization.

Account anonymization has no approved CLI entry point or cron worker. A 30-day deadline does not mean automatic anonymization. Do not recreate the removed CLI or schedule it without separate review and explicit approval.

Phase 2's automated baseline is merged; the broader manual security review is not marked complete.

## 9. Local runtime findings (2026-10-31)

The owner verified the following on Kali/XAMPP:
- Apache workers run as `daemon`.
- The `storage/logs` access/default ACL was tightened to remove `other` access while retaining `daemon` write access. Two real create-file tests as `daemon` succeeded; new files inherited `other::---`.
- The daemon sudo policy permits only `/usr/bin/bash /home/a/bin/zayan-hardware-update.sh` as user `a`. The external script is mode `0700`, owned by `a:a`, and requires a clean `main` worktree.
- The local checkout remains on `local/audit-update-20261031` with a modified `.htaccess` and untracked `.htaccess.before-rewrite-fix`, `composer.lock`, Python cache directories, `update.sh`, and `zayan_hardware_audit.sql`. Do not run the updater, pull, reset, clean, or switch branches until these files are preserved and reconciled.
- The local project-directory backup inventory found no full database or private-media backup archive. Do not improvise a restore or use the live database as a target.

**Verified:** Apache log confidentiality and write access. **Not verified:** owner database restore, private-media restore, host updater execution, or hosted deployment.

## 10. Working rules for all three phases

1. Follow the relevant scope and pass criteria in [COMPLETION_PLAN.md](COMPLETION_PLAN.md); do not broaden each phase into an unbounded audit.
2. Inspect branch, commit, and `git status` before editing. Preserve modified and untracked local files.
3. Use disposable synthetic databases for CI and isolate any restore test in a newly named database.
4. Run focused regression tests for every fix, then the broader phase gate.
5. Update [UPDATES.md](../UPDATES.md), this file, and the relevant feature/runbook/architecture documents with verified facts.
6. At the end of each phase, report passed checks, failed checks, blocked local checks, changed files, and the next phase.

Do not mark a phase complete while its acceptance criteria remain unverified.


## 2026-10-31 — Object-level authorization regression expansion

- Extended `tests/php/issue-authorization-integration.php` to reject cross-user edits and soft-deletes of technical posts, edits/deletes of another author's replies, unauthorized diagnostic creation/edit/delete, and accepting a solution on another member's help request. Existing moderator/admin result-review and self-vote protections remain covered.
- Extended `tests/php/help-request-media-integration.php` to verify requester/helper access to private request and attachment metadata, reject a nonparticipant, and reject a nonparticipant attachment write.
- These are focused model/service integration cases, not a claim that every POST route has exhaustive end-to-end authorization coverage. The remaining route-by-route authorization review stays open until all handlers and state transitions are reconciled.
