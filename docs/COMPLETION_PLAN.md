# Zayan Hardware — Three-Phase Completion Plan

**Owner-approved direction:** Complete the remaining stabilization work in three controlled phases. The owner explicitly approved consolidation of the accumulated feature and Phase 1/2 baseline into `main` on 2026-10-31. Merging code is not deployment approval; production rollout still requires the relevant acceptance gates.

## Operating rules

- Work from the live branch/commit, not an old memory snapshot. Preserve local modified and untracked files.
- Keep all project data, especially technical posts, replies, diagnostics, attachments, schematics, edit history, and attribution.
- Use disposable synthetic databases for CI. Do not run destructive migrations, account-retention workers, media cleanup, or restore operations against a real database without explicit approval, a verified backup, and a rollback plan.
- Run focused checks for each change. Run broad checks at phase gates, not after every small commit.
- Update `UPDATES.md`, `docs/CURRENT_STATE.md`, and relevant technical docs as part of delivery.
- Report implemented, locally tested, CI-tested, runtime-verified, and deployed as separate states.

## Phase 1 — Database, data integrity, and operational reliability

**Goal:** Prove that a clean installation, core data flows, logs, and backup/restore procedures are dependable.

Scope:
1. Verify the ordered migration sequence 000–018 on supported MySQL/MariaDB versions using disposable databases.
2. Verify schema objects and data preservation: tables, columns, indexes, constraints, foreign keys, triggers, and representative row counts.
3. Exercise a complete disposable-database backup → compression/checksum → restore → schema/data comparison cycle in CI.
4. Review and test core write transactions and file recovery: posts, replies, diagnostic updates, edit history, issue attachments, drafts, and personal-help attachments.
5. Verify the PHP/CLI bootstrap, PHP and JavaScript syntax, public/private route policy, and focused runtime smoke paths.
6. Verify application, access, security, and audit log channels; test secret redaction, HTTP shutdown access logging, and retention behavior; document safe filesystem permissions and local Apache checks.
7. Add a read-only schema comparator covering tables, views, columns, indexes, foreign keys, CHECK constraints, triggers, routines, events, and optional table row counts.
8. Add a disposable CI backup → gzip/checksum → restore-to-new-schema → schema/row-count comparison gate across the database matrix.
9. Document safe triage for `storage_recovery_tasks` without deleting any referenced file; do not add an automatic worker until its reference checks and failure behavior are covered by tests.
10. Complete the owner's existing local database backup restore and live-schema/row-count comparison only on a separate, explicitly named restore-test database.
11. Separately inventory and sample-restore the private media backup, then verify that representative restored attachment/audio paths referenced by the database exist under the private storage root. This requires the owner's local machine and must not overwrite the live storage tree.

Exit criteria:
- The complete 000–018 migration sequence and selected integration suites pass on the supported CI database matrix.
- The disposable backup/restore cycle passes and schema objects plus row counts match.
- Core write/media failure tests pass.
- Logging channels, redaction, and retention tests pass; local Apache write access is separately verified.
- The local owner database backup has a recorded restore result and schema/data comparison, or is explicitly marked blocked pending the owner running the documented local command sequence.
- The private media backup is separately verified and representative DB-referenced files resolve in an isolated restore tree, or that check is explicitly blocked because no media backup is available.
- No data-loss or destructive-operation risk remains unaddressed.

**CI implementation and verification checkpoint (2026-10-31):** The read-only comparator, disposable backup/restore/checksum gate, log redaction/channel/retention tests, and real HTTP access-shutdown test are implemented. On commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab`, the PHP workflow (`41219585375`), Graphify workflow (`41219585292`), and database workflow (`41219585295`) all completed successfully. The database workflow passed its full 000–018 migration and integration matrix on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11, including the backup/restore/schema/row-count gate. The local owner backup restore, private-media sample restore, and actual Apache-user permission check remain pending.

**Status:** Automated Phase 1 CI gate passed. Phase 1 remains open for owner-machine acceptance checks; no live database, private-media tree, or deployment was modified.

## Phase 2 — Security, lifecycle, and dependency integrity

**Goal:** Close the remaining authorization/security gaps and make system relationships understandable.

Scope:
1. Complete the POST-route authorization matrix: authentication, role, ownership/participation, CSRF, rate limiting, validation, and state-transition rules.
2. Review RBAC and sensitive admin writes, session lifecycle, password/reset flows, account retention/anonymization, and consent/audit behavior.
3. Review SQL access, XSS output encoding, CSRF boundaries, rate limits, upload MIME/size/dimension checks, path containment, private file delivery, and error disclosure.
4. Inspect pending recovery-task reconciliation and notification failure behavior; implement only safe, testable recovery/retry behavior where justified.
5. Run Graphify and reconcile its output with route/API maps; inspect dynamic JavaScript API references manually.
6. Document controllers, services, models, views, routes, database relations, storage paths, and feature lifecycles. Fix confirmed bugs with focused regression tests.

Exit criteria:
- No known critical/high security findings remain without a documented owner decision.
- Authorization and lifecycle checks have direct regression coverage.
- Graphify output is deterministic and reviewed; unresolved edges/warnings are explained.
- Architecture and feature maps match the actual source.

### Phase 2 implementation checkpoint

Implemented on `phase2/security-graphify-20261031`; the exact latest CI head must pass after the most recent account lifecycle updates:

- Generic public login errors for invalid credentials and suspended accounts; blocked-account attempts count against the same IP/identifier rate limits.
- Per-account `auth_version` migration 019. Administrator password resets, account status changes/reactivation, role changes, member deletion requests, and final identity anonymization increment the version, invalidating every previously issued session.
- Login/session verification is bound to the version checked with the password. Conditional password rehash updates prevent an in-flight old-password login from overwriting a newer administrator-reset hash.
- Regression tests cover stale sessions, password-reset/login races, suspension/reactivation with an old cookie, generic login error messages, and a static policy over every account lifecycle mutation.
- Automated CSRF policy test covers all 49 declared POST routes, including explicit `accept/decline → respond` delegation, and a strict public POST allow-list of `/login` and `/register`.
- Admin site update is protected by CSRF, per-user rate limiting, an active-admin database recheck, fixed command arguments, audit logging, and the shared account lifecycle advisory lock held through command completion. Its external script and sudoers rule still require local inspection.
- A frontend sink review covered all 13 JavaScript files and the key issue/help/profile/auth/admin templates. The only `innerHTML` sinks are fixed SVG fragments and fixed platform-specific PWA guide copy; dynamic template text uses the shared HTML escape helper, and composer JSON is emitted with `JSON_HEX_*`.
- Graphify artifact was inspected; five dynamic API nodes were manually mapped to Push endpoints, username availability, and generic Service Worker navigation/static requests. The duplicated 002 migration prefix remains a documented sequential order, not an alternate migration.
- See [POST route security matrix](SECURITY_POST_ROUTE_MATRIX.md), [security hardening](security-hardening.md), and [JavaScript API route map](javascript-api-route-map.md).

Exact-head CI verification: commit `889dd99c74824674789a138bfc7dd3b9dd30444e` passed all three workflows: [PHP syntax/security](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933680), [Graphify](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933760), and [database migration integration](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41222933686). The remaining Phase 2 gate includes the full object ownership/participant and model-boundary review, broader SQL/input validation, complete XSS/output review, private file/upload lifecycle and containment, storage recovery/notification retry behavior, and local verification of the external updater/sudoers. No Phase 2 completion is claimed yet.

## Phase 3 — UI, PWA, cross-device acceptance, and final readiness

**Goal:** Verify the product experience and issue a truthful release-readiness report.

Scope:
1. Finish outstanding header, guest landing/help-library visibility, login state styling, policy/help consistency, and other owner-approved UI details.
2. Verify Arabic RTL layouts at desktop and mobile breakpoints and check keyboard/accessibility behavior for core forms.
3. Test PWA install, launch, update, cache/offline behavior, and push notification journeys on Android and iOS Safari where available.
4. Run end-to-end journeys: registration/login, profile, technical post with media, reply tree, diagnostic update, draft save/publish, private help conversation, admin workflows, account deletion request, and retained technical history.
5. Re-run the release test matrix on the final candidate commit and review all diffs/migrations/documentation.
6. Publish the final acceptance report with verified results, known limitations, local manual checks, and deployment status.

Exit criteria:
- Required UI and end-to-end journeys pass on desktop and supported mobile browsers/devices.
- Final CI is green for the exact candidate commit.
- No unreviewed migrations, local changes, or untracked release artifacts remain.
- Deployment is marked complete only after a real deployment and smoke test.

## Phase status

| Phase | Status | Gate |
|---|---|---|
| Phase 1 — Foundation and operations | **Automated CI passed; merged into `main`; owner acceptance pending** | PR #17 merged; owner database backup restore and private-media sample restore remain unverified |
| Phase 2 — Security and Graphify | **Automated baseline passed; merged into `main`; manual audit follow-up open** | PR #18 merged; exact tested head `c23dde25`; route-by-route authorization, SQL/input, XSS, file-edge and recovery reviews remain |
| Phase 3 — UI, PWA, final acceptance | **Not started** | Browser/mobile/PWA acceptance and final readiness report remain |

## Local runtime acceptance note (2026-10-31)

The owner verified that Apache runs as `daemon`. The `storage/logs` ACL was tightened to remove `other` access while preserving `daemon` write access; two real file-creation tests succeeded and new files inherited `other::---`.

The owner checkout remains on `local/audit-update-20261031` with modified `.htaccess` and untracked files. The external updater requires a clean `main` worktree and was **not** run. Do not pull, reset, clean, or switch branches until local work is preserved and reconciled.

The project-directory backup inventory found no full database backup or private-media archive. Owner database restore and private-media sample restore remain pending; no live database or private-media tree has been modified.

## Current branch and verification note

- PR #17 merged into `main` as `94ad4e077de6d0698ca1f2dad138a9fa9ada62cf`.
- PR #18 merged into `main` as `dadddde53f394a0fe353b575ec13dbeb85749f6e`.
- The authoritative development branch is `main`; current baseline includes the accumulated feature stack plus Phase 1 and Phase 2 automated baselines.
- Phase 1 latest workflow head `4bf013f81fc71bc32f8b221fefc1c778d00419d8`: PHP run 41238247509, Graphify run 41238247478, and database matrix 41238247686 all passed.
- Phase 2 exact tested head `c23dde25f2bee53068c85e775ca56fa93f52e0a9`: PHP run 41238382181, Graphify run 41238382162, and database matrix 41238382193 all passed.
- No live database migration, media restore, or deployment is claimed.
