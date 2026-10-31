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
6. Verify application, access, security, and audit log channels; test secret redaction and retention behavior; document safe filesystem permissions and local Apache checks.
7. Add a read-only schema comparator covering tables, views, columns, indexes, foreign keys, CHECK constraints, triggers, routines, events, and optional table row counts.
8. Add a disposable CI backup → gzip/checksum → restore-to-new-schema → schema/row-count comparison gate across the database matrix.
9. Document safe triage for `storage_recovery_tasks` without deleting any referenced file; do not add an automatic worker until its reference checks and failure behavior are covered by tests.
10. Complete the owner's existing local database backup restore and live-schema/row-count comparison only on a separate, explicitly named restore-test database.\n11. Separately inventory and sample-restore the private media backup, then verify that representative restored attachment/audio paths referenced by the database exist under the private storage root. This requires the owner's local machine and must not overwrite the live storage tree.

Exit criteria:
- The complete 000–018 migration sequence and selected integration suites pass on the supported CI database matrix.
- The disposable backup/restore cycle passes and schema objects plus row counts match.
- Core write/media failure tests pass.
- Logging channels, redaction, and retention tests pass; local Apache write access is separately verified.
- The local owner database backup has a recorded restore result and schema/data comparison, or is explicitly marked blocked pending the owner running the documented local command sequence.\n- The private media backup is separately verified and representative DB-referenced files resolve in an isolated restore tree, or that check is explicitly blocked because no media backup is available.
- No data-loss or destructive-operation risk remains unaddressed.

**Implementation checkpoint (2026-10-31):** A read-only comparator is now present at `bin/compare-database-schema.php`; the database workflow now includes an isolated backup/restore/checksum/metadata-and-row-count check; the logging redaction test now covers raw Authorization/Cookie headers, Bearer/query tokens, all four log channels, JSON records, and retention. A local owner backup/restore runbook was added at `docs/DATABASE_BACKUP_RESTORE.md`; a safe read-only storage recovery triage guide was added at `docs/STORAGE_RECOVERY_RUNBOOK.md`. These changes are implemented in the Phase 1 branch, but the tests have not yet executed on this branch. The owner-machine restore and Apache/browser runtime checks remain pending.

**Status:** In progress. The existing workflow defines the 000–018 three-engine matrix and many integration tests, with a new backup/restore gate. The last recorded PR #16 CI run executed zero steps; recheck Actions quota before starting a new run. No current Phase 1 test pass is claimed.

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
| Phase 1 — Foundation and operations | **In progress** | Current branch: `phase1/foundation-reliability-20261031` |
| Phase 2 — Security and Graphify | Planned | Starts after Phase 1 gate |
| Phase 3 — UI, PWA, final acceptance | Planned | Starts after Phase 2 gate |

## Current branch and verification note

- This plan is being implemented on `phase1/foundation-reliability-20261031`, based on the merge commit of PR #16 (`098442813eb5a55e96d5fff27ce127f9b5f08591`).
- PR #16 merged into `audit/content-retention-consistency`, not `main`.
- The last recorded GitHub Actions jobs for the merged PR head failed before executing any steps because the included Actions minutes were exhausted. Such runs are neither test failures nor successful verification.
- Recheck live Actions usage/budget before opening a PR or running workflows. Keep workflow definitions and test matrices intact.
