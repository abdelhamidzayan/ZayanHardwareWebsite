# Zayan Hardware — System Rebuild Audit

**Audit snapshot:** 2026-10-31  
**Working branch:** `audit/system-rebuild-20261031`  
**Current verified CI head:** `55522a3cc204e961aaf9947773b15ff10321ad02`  
**Working branch starts from:** `f5d872983fdd71da47ef1a61491d81efdc5b42e4`  
**Status:** Several foundational fixes are implemented and CI-verified. This is not a full project sign-off.

## Owner direction and data boundary

The owner has confirmed that legacy/demo content is not a blocker and that the database will be renewed. This allows a deliberate fresh-database bootstrap plan. No database has been dropped, truncated, reset, or otherwise modified by this audit. The owner's Kali/XAMPP database and backup files have not been accessed by GitHub CI.

The new application must still protect technical content created after launch. Published posts, replies, diagnosis updates, repair outcomes, edit history, attachments, reputation, approved help knowledge, identity attribution and consent/audit records must not be physically deleted by normal application paths.

## Exact GitHub state

- Default branch: `main` at `8732fd70e3a85ecfb2c8e94c69fd7f4a6ebfa932`.
- PR #14 remains Draft/open/unmerged. Base `feature/profiles-help-media`; head `fix/audit-priority-issues` at `74ffd99bd0a884fb2b09e28559aa948321020fba`.
- PR #15 remains Draft/open/unmerged. Base `fix/audit-priority-issues`; head `audit/content-retention-consistency` at `f5d872983fdd71da47ef1a61491d81efdc5b42e4`.
- PR #16 is Draft/open/unmerged. Base `audit/content-retention-consistency`; head `audit/system-rebuild-20261031`.
- PR #14 is not based on `main`. No PR was merged, retargeted or deployed.
- The PR #16 base relationship is deliberate: this audit work is a child branch of the audit baseline, not a proposed direct merge to `main`.

## Latest CI verification

All three latest workflow runs completed with conclusion `success` on head `55522a3cc204e961aaf9947773b15ff10321ad02`:

| Workflow | Run | Result |
|---|---:|---|
| PHP syntax + CLI boot + RateLimiter API + draft rollback + route policy + exception log redaction + JS syntax | 41180453075 | Success |
| Graphify unit tests + graph generation + deterministic `--check` + artifact upload | 41180453042 | Success |
| Fresh install + migration integration on MySQL 8.0, MariaDB 10.4.32 and MariaDB 10.11 | 41180453039 | All three jobs success |

The database logs were inspected, not just the green status:
- The full `000 → 017` migration sequence completed on all three engines.
- Each engine created the first administrator for a synthetic owner account and refused a second bootstrap because an administrator already exists.
- The full fresh-install smoke test passed with 73 foreign-key references.
- The protected delete triggers/foreign keys were present, and attempts to physically delete a standalone technical post, reply, attachment, a post with dependent records, and a user were rejected.
- Reply tombstone integration passed; parent-child ancestry remained intact after soft-deleting the parent.
- Existing sequential 002 migration and database integration steps also completed in the workflow.

## Verified implementation on this branch

### Guest access and route contracts

- Router guest access is now declared on the route itself using `getPublic()` / `postPublic()`; ordinary `get()` / `post()` routes remain private.
- Public pages include the documented landing/help/policies, authentication screens, member profiles, public avatars and consent-approved help-library pages.
- Drafts, feed/discussion pages, private attachments, personal inbox/messages, account actions and admin routes remain private to authenticated users.
- `AvatarController::show()` now accepts the raw route parameter and validates the positive integer, avoiding the previous strict-types mismatch for `/avatar/{userId}`.
- `tests/php/router-public-routes.php` checks the public/private policy and route parameter contract.

### New database installation

- The repository was missing the source-controlled base schema for `users` and `technician_profiles`. `000_core_identity_schema.sql` now provides that baseline for a new empty database.
- `database/FRESH_INSTALL.md` documents the exact supported order from 000 through 017.
- The duplicate 002 prefix is historical. `002_final_registration_permissions.sql` and `002_registration_wizard.sql` are sequential in the verified fresh-install path, not alternatives. Never infer applied state from filenames or replay the sequence on an existing database.
- `bin/bootstrap-first-admin.php` is a CLI-only, exact-database-confirmed, advisory-lock-protected operation. It promotes an existing active owner account only when no admin currently exists and writes a security audit record. CI verifies both the first promotion and rejection of a second active account.
- The owner’s existing local migrations 014 and 015 remain untouched and must not be replayed there.

### Protection of technical history

- Migration 016 changes 43 content/attribution foreign keys to `ON DELETE RESTRICT`, preventing cascading loss of technical history and help records.
- Migration 017 creates 23 `BEFORE DELETE` triggers for protected technical, identity, personal-help, consent, skill-evidence and audit tables. These guards also prevent deleting a standalone post/reply with no dependent rows.
- These migrations only alter constraints/triggers; they contain no `DELETE`, `TRUNCATE` or content rewrite.
- Draft deletion/media cleanup, reply vote toggles, and transient notification/push subscription cleanup remain separate explicit lifecycles. The CI verifies these protections against disposable synthetic data only.
- Physical delete guards supplement application soft deletion and account anonymization; they do not replace them.

### Reply-tree retention

- `Issue::deleteReply()` no longer sets child `parent_reply_id` to NULL.
- A deleted parent remains in the query as a tombstone only when it still has active children. The view hides the deleted text but preserves the parent node and child relationship.
- Integration test creates a synthetic post, parent reply and child reply; after deleting the parent, both rows remain, the child keeps its original parent id, and the query returns the tombstone and child. It also proves physical parent deletion is rejected.

### Logging privacy

- `App::exceptionContext()` / `App::logException()` provide a common safe exception representation. Arbitrary exception text is not copied; PDO diagnostics retain SQLSTATE/driver code only.
- Personal-help attachment, Web Push and account-retention exception logging use the safe helper. The retention CLI no longer prints an arbitrary exception message.
- `tests/php/logging-exception-redaction.php` injects synthetic bearer tokens, URL tokens and SQL secrets and confirms none appear in the temporary application log. The test passed in CI.

## Graphify snapshot

The successful Graphify run on this branch scanned:
- 138 files
- 72 routes
- 160 literal SQL query fragments
- 38 tables and 61 statically detected FK references
- 15 JavaScript API sites: 10 linked and 5 unresolved
- 652 nodes and 1,464 edges
- 19 migration files

The duplicate 002 prefix warning remains intentionally visible. Documentation now explains the sequential 002 order and the CI test validates it. Graphify is static analysis only; the five unresolved JS API sites still require manual review. The generated graph is not proof of runtime behavior.

## Confirmed gaps that still need work

1. **Content write orchestration:** issue creation, reply/diagnostic creation and edits still span DB and filesystem operations without one application-service transaction/compensation boundary. A failure between issue/reply row creation, file moves, attachment inserts and inline-token update can leave partial state.
2. **Draft media lifecycle:** revision locking is present, but row deletion and file cleanup are not one recoverable operation; failed unlinks can leave orphaned files. Rendered-file replacement also needs a durable cleanup/reconciliation record.
3. **Personal-help media:** the message/request transaction and attachment writes remain separate. The service currently attempts immediate unlink on DB failure instead of recording a retryable compensation.
4. **Upload policy consistency:** regular issue and personal-help upload validation checks byte size and detected MIME but still lacks the common decoded-image width/height/pixel limit and a shared decompression-bomb policy. Malformed personal-help upload structures are not rejected consistently with ContentEditorService.
5. **Ownership/auth review:** the initial pass found useful participant scoping and CSRF, but every POST route still needs a full systematic matrix covering auth, role, owner/participant, state transitions, CSRF, rate limit, validation and Model invariants.
6. **JS API traceability:** five API calls remain unresolved in Graphify and require manual DOM/view/script review.
7. **Browser/PWA:** no automated browser journeys yet for registration/login, post composer, mobile layout, offline transitions, Safari install behavior, push subscribe/delivery, or attachment streaming.
8. **Backup/restore:** the latest owner backup `zayan_hardware_full_verified_20261031_023151.sql.gz` has not been re-restored in this session. The earlier separate-DB restore/count comparison is prior evidence only, not a new restore validation.
9. **Live schema comparison:** fresh-install CI validates a clean database. The owner's current local schema and applied migration state have not been introspected in this turn; no migration 016/017 should be applied to it until an explicit comparison and backup plan exist.
10. **Runtime/storage ACL:** no new Kali/XAMPP/Apache filesystem-permission test or live HTTP request was executed in this pass.

## Next implementation order

1. Build a single content-write application service for issue/reply/diagnostic create/edit with DB transactions, staged file moves, explicit compensation and durable error reporting.
2. Add failure-injection integration tests for each DB/filesystem boundary, including failure during compensation.
3. Create shared UploadPolicy for MIME, byte limit, dimensions/pixel count, and consistent malformed upload rejection; test malicious and decompression-heavy samples.
4. Make draft and personal-help media cleanup/replacement recoverable and observable.
5. Finish route/controller/model authorization matrix and resolve the five JS API links.
6. Add browser automation and PWA/mobile checks.
7. Inspect the actual local database schema/history and validate the backup restore on a separate disposable database after owner runs one explicit local command sequence.

## Verification ledger

| Area | State |
|---|---|
| PR/branch ancestry and status | Verified through GitHub API |
| PR #16 route/public policy regression | CI passed |
| PHP lint, CLI boot, RateLimiter API and existing draft rollback test | CI passed |
| Exception log redaction regression | CI passed |
| Graphify 10 unit tests, graph generation and deterministic check | CI passed |
| Fresh install migrations 000–017 on MySQL 8.0 / MariaDB 10.4.32 / MariaDB 10.11 | CI passed |
| First-admin bootstrap success and second-admin rejection | CI passed |
| Physical delete guards and FK RESTRICT checks | CI passed on all three engines |
| Reply tombstone / ancestry integration | CI passed on all three engines |
| Full issue/reply/diagnostic DB+filesystem failure injection | Not implemented/tested |
| Common image dimension/decompression limits | Not implemented/tested |
| Full POST authorization matrix | Partial static review only |
| Five unresolved JS API sites | Not resolved yet |
| Browser/mobile Safari/PWA journeys | Not tested |
| Latest owner backup restore | Not re-run in this audit |
| Current local DB schema comparison / migration 016-017 | Not performed |
| Production deployment or PR merge | Not performed |

Do not describe the full project as complete until the remaining gaps are implemented or explicitly accepted and every required runtime/local check has been executed.
