# Zayan Hardware — System Rebuild Audit

**Audit snapshot:** 2026-10-31  
**Working branch:** `audit/system-rebuild-20261031`  
**Branch starting point:** `f5d872983fdd71da47ef1a61491d81efdc5b42e4`  
**Application source reviewed:** `audit/content-retention-consistency` at the same commit  
**Status:** Initial source and CI audit; not a final sign-off.

## Owner direction and data boundary

The owner has confirmed that legacy/demo content is not a blocker and that the database will be renewed. This permits a deliberate fresh-database bootstrap plan. It does **not** mean that this audit has dropped, truncated, or modified any database, nor that a deployed database has been reset.

The new system must still protect content created after launch from accidental loss. Routine post, reply, diagnosis, repair-result, and attachment workflows should use explicit soft-delete/retention rules; intentional database provisioning must be a separate, loudly gated operator action.

## Exact GitHub state observed

- Default branch: `main` at `8732fd70e3a85ecfb2c8e94c69fd7f4a6ebfa932`.
- PR #14: Draft, open, not merged. Base `feature/profiles-help-media`; head `fix/audit-priority-issues` at `74ffd99bd0a884fb2b09e28559aa948321020fba`.
- PR #15: Draft, open, not merged. Base `fix/audit-priority-issues`; head `audit/content-retention-consistency` at `f5d872983fdd71da47ef1a61491d81efdc5b42e4`.
- PR #14 is not based on `main`. Do not merge or retarget either PR to `main` without a deliberate ancestry and diff reconciliation.
- The rebuild branch was created from PR #15's exact head. No PR was merged, and no deployment was performed.

## CI actually observed

All listed runs completed with conclusion `success`:

| PR head | Workflow | Run |
|---|---|---:|
| PR #14 — `74ffd99` | PHP syntax + CLI boot + RateLimiter API + JS syntax | 41152043380 |
| PR #14 — `74ffd99` | Graphify tests + graph generation + artifact upload | 41152043407 |
| PR #14 — `74ffd99` | DB integration on MySQL 8.0, MariaDB 10.11, MariaDB 10.4.32 | 41152043425 |
| PR #15 — `f5d8729` | PHP syntax + CLI boot + RateLimiter API + draft rollback test + JS syntax | 41152573172 |
| PR #15 — `f5d8729` | Graphify tests + graph generation + artifact upload | 41152573227 |
| PR #15 — `f5d8729` | DB integration on MySQL 8.0, MariaDB 10.11, MariaDB 10.4.32 | 41152573184 |

The database workflow verifies the sequential `002_final_registration_permissions.sql` → `002_registration_wizard.sql` path on a synthetic pre-registration schema; tests the 015 contact constraint there; applies 011–014 to a prepared synthetic pre-feature schema; checks representative foreign keys/writes; and runs rate-limiter and account-retention integration tests. This is meaningful database coverage, but it is not a complete replay of every migration 001–015 from an empty database.

PR #15's new test exercises `DraftPublishingService::restoreMovedFiles()` with a successful move-back and a failed restore that must retain the destination file. It does not yet exercise the full publishing operation with injected failures at every DB/filesystem boundary.

## Confirmed architecture and review findings

### Good foundations already present

- PHP MVC boundaries exist: Core, Controllers, Services, Models, Views; route declarations are centralized in `config/routes.php`; public document root is `public/`.
- PDO uses exceptions, associative fetches, native prepares, and UTC DB timezone.
- Login uses CSRF, a dummy password hash for unknown users, IP + identifier rate limiting, and session ID regeneration. Registration has CSRF and IP rate limiting.
- Private attachment routes use authentication, real-path containment checks and MIME allow-lists; personal-help attachment lookup is participant-scoped; draft media lookup is owner-scoped.
- The account-retention service preserves user IDs rather than physically deleting accounts; it has disposable-database integration coverage.
- Graphify is deterministic static analysis and explicitly documents its dynamic-dispatch and runtime limits.

### Confirmed consistency gaps

1. **Issue write orchestration:** normal issue creation inserts the issue, then stores attachment files/rows, then rewrites inline tokens in a separate update. Reply and diagnostic-update flows follow a similar multi-step pattern. There is no encompassing application-service transaction and filesystem compensation for the entire operation. An exception between steps can leave a partially completed operation.
2. **Edit orchestration:** reply/diagnostic edit history and body changes are committed in model transactions, but new media is stored afterwards by the controller. A later failure can leave an edit and media in different states.
3. **Reply-tree history:** `Issue::deleteReply()` sets direct children’s `parent_reply_id` to NULL before soft-deleting the parent. Text is retained, but the historical parent-child relationship is lost.
4. **Upload validation consistency:** `ContentEditorService::validateUploads()` and `HelpRequestMediaService::validate()` check upload errors, byte size and detected MIME. They do not enforce common decoded image dimension/pixel limits. The personal-help validator also returns an empty list for malformed non-array upload structures rather than rejecting them.
5. **Personal-help media orchestration:** the service moves each upload and then inserts its attachment row. A DB failure attempts to unlink the moved file; this is not a single recoverable DB/filesystem operation.
6. **Draft cleanup semantics:** draft media deletion commits the row deletion before removing private files; failed filesystem removal can leave orphaned files. These operations need explicit retry/reconciliation semantics.
7. **Migration operations:** there are two migration files with prefix `002_`, and the workflow handles their sequential order explicitly. Migration 013 uses `CREATE TABLE IF NOT EXISTS` for the terms acceptance table also present in the 002 path. The repository tree has no dedicated migration runner/version ledger; the workflow invokes SQL files explicitly.
8. **Test coverage boundary:** there are no current CI steps for HTTP-level issue lifecycle integration, real upload MIME/dimension cases, concurrent composer sessions, browser/device journeys, mobile Safari/PWA behavior, or end-to-end push delivery.
9. **Static analysis limits:** Graphify previously reported 15 JavaScript API sites, 10 statically linked and 5 unresolved. Each unresolved call requires manual view/script/runtime tracing; a missing edge is not proof of a missing dependency.

## Build order for this branch

1. Define the fresh-install schema and an explicit migration manifest for a **new disposable database**; never infer applied state from filenames and never replay migrations 014/015 against the already-migrated local database.
2. Introduce a tested content-write application service that coordinates DB transactions, staged files, compensation, and a recoverable failure record for issue, reply and diagnostic flows.
3. Unify upload policy: detected MIME, strict byte limits, decoded image dimension/pixel limits, safe generated paths, ownership, and consistent malformed-input rejection.
4. Preserve reply ancestry through tombstone/soft-delete presentation instead of nulling child-parent links.
5. Make draft and personal-help file lifecycle operations recoverable and observable; do not silently drop failed file cleanup or restoration.
6. Review every POST route against authentication, CSRF, role/ownership/participant authorization, validation, rate limiting and model-level invariants.
7. Add disposable-DB integration tests and failure injection for success, intermediate failure, rollback, commit failure and compensation failure.
8. Add browser-level journeys for registration/login, new post, attachment viewing, reply tree, diagnosis, drafts, profile/help, notifications and PWA install/cache behavior.
9. Regenerate Graphify, reconcile unresolved API calls, and update the architecture map from the verified source.

## Verification ledger

| Area | State |
|---|---|
| Repository/PR/branch state | Verified through GitHub API |
| PR #14 and #15 workflow conclusions | Verified; all listed runs succeeded |
| MVC/routes and key content paths | Initial static source review |
| Authentication and login rate limits | Initial static source review |
| Migration DDL/foreign-key declarations | Initial static review; not a live schema introspection |
| Issue/media end-to-end failure scenarios | Not yet tested |
| Full migration replay from empty schema | Not yet tested |
| Local Kali/XAMPP browser/runtime | Not accessible through GitHub connector |
| Backup restore of latest local backup | Not re-run in this audit |
| PWA/mobile browser journeys | Not tested |
| Production deployment or database changes | Not performed |

Do not mark the project complete until every remaining row is either tested or explicitly accepted as a known limitation.
