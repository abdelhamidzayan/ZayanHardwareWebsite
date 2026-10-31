# Zayan Hardware — Three-Phase Completion Plan

**Owner-approved direction:** Complete the remaining stabilization work in three controlled phases. Keep changes on dedicated Git branches and do not merge to `main` or deploy without separate approval.

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

Implemented on `phase2/security-graphify-20261031`, pending execution of the Phase 2 CI suite:

- Generic public login errors for invalid credentials and suspended accounts; blocked-account attempts count against the same IP/identifier rate limits.
- Per-account `auth_version` migration 019. Administrator password resets increment the version and invalidate prior sessions.
- Login/session verification bound to the version checked with the password. Conditional password rehash updates prevent an in-flight login using an old password from overwriting an administrator-reset hash.
- Regression test for stale-session invalidation and the concurrent password-reset/login race.
- Automated CSRF policy test for every declared POST route (49 current declarations), including the explicit `accept/decline → respond` delegation, and a strict public POST allow-list of `/login` and `/register`.
- [POST route security matrix](SECURITY_POST_ROUTE_MATRIX.md).

No Phase 2 tests are claimed as passing until the draft PR workflows execute on the current branch head. The full authorization matrix and Graphify review remain in progress; further findings may require additional fixes.

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
| Phase 1 — Foundation and operations | **CI passed; local acceptance pending** | Current branch: `phase1/foundation-reliability-20261031`; PR #17 remains draft |
| Phase 2 — Security and Graphify | **In progress; CI pending** | Current branch: `phase2/security-graphify-20261031`; draft PR pending |
| Phase 3 — UI, PWA, final acceptance | Planned | Starts after Phase 2 gate |

## Current branch and verification note

- This plan is being implemented on `phase1/foundation-reliability-20261031`, based on the merge commit of PR #16 (`098442813eb5a55e96d5fff27ce127f9b5f08591`).
- PR #16 merged into `audit/content-retention-consistency`, not `main`.
- Earlier PR #16 runs executed zero steps due the old private-repository included-minutes limit. After the repository became public, PR #17 executed the current Phase 1 workflows successfully on commit `5723fb26f02d14a0d66cc5db43093a8fd1a08aab`.
- Run IDs: PHP `41219585375`; Graphify `41219585292`; database matrix `41219585295`. All jobs and steps completed successfully.
- PR #17 is still a draft targeting `audit/content-retention-consistency`, not `main`. Do not merge it to `main` or mark it deployed.
