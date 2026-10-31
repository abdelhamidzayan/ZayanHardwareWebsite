# Zayan Hardware — AI Context and Handoff

> **Start here in every new AI conversation.** This file is the durable project handoff. For the actual source architecture, open [Architecture and dependency map](docs/architecture-code-map.md). For the automated source graph, read [Graphify](docs/graphify.md).

## 1. Project identity and scope

- Product: **Zayan Hardware — عصارة الورش**.
- Repository: `abdelhamidzayan/ZayanHardwareWebsite`.
- Purpose: an Arabic-first, RTL technical community and knowledge system for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.
- Stack: custom PHP 8.2+ MVC, MySQL/MariaDB, vanilla JavaScript/CSS, responsive web UI, PWA and Web Push.
- Required architecture: Core → Controllers → Services / Models → Views; route definitions in `config/routes.php`; public document root is `public/`.
- Explicitly out of scope: CRM/customer management and mobile software-repair features, unless the owner explicitly changes the scope.

## 2. Mandatory reading order

1. `AGENTS.md` — non-negotiable safety and contribution rules.
2. [Architecture and dependency map](docs/architecture-code-map.md) — request lifecycle, route/controller/service/model/view relationships.
3. [Graphify guide](docs/graphify.md) — generate/check the machine-readable source graph and understand its limitations.
4. [Security hardening](docs/security-hardening.md) — rate limits, private media, account retention.
5. [Post composer and drafts](docs/post-composer-and-drafts.md) and [profiles/personal help](docs/profiles-personal-help.md) when working on those features.
6. [Account retention policy](docs/account-retention.md) and [content-system migrations](docs/content-system-migrations.md) before touching account lifecycle or schema.
7. Read the actual source at the active branch before changing anything. Documentation is a map, not a substitute for source inspection.

## 3. Source-of-truth boundaries

Never conflate these states:
- GitHub default branch;
- an open pull-request branch;
- the owner's local Kali checkout;
- the running local XAMPP site;
- any hosted/production deployment;
- the live database and its applied migration history.

Always report the exact branch and commit being discussed. A passing CI job does not prove local browser/PWA behavior or production deployment. A migration file existing in Git does not prove it ran on a database.

## 4. Current verified GitHub snapshot — 2026-10-31

- Active audit/fix branch: `fix/audit-priority-issues`.
- Application-code and CI verification baseline: `46960cf9efb33b2b0affa503d5e832725bdba4b4`. README/AI handoff documentation commits were added after this baseline on the same PR branch; always resolve the live branch HEAD before starting new work.
- PR #14 is a **Draft**; it is not merged and must not be described as deployed.
- On that exact head, GitHub Actions completed successfully:
  - Graphify dependency graph: run [41138280152](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41138280152).
  - PHP syntax check: run [41138280122](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41138280122).
  - Database migration integration: run [41138280074](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41138280074).
- Graphify artifact: `zayan-hardware-graphify`, artifact ID `13017416033`, expires 2026-11-13. It contains `.graphify/graph.json`, `.graphify/architecture.mmd`, and the summary.
- Latest observed Graphify summary: status `ok`; 130 source files scanned; 84 routes; 161 SQL queries analyzed; 15 API calls (10 linked, 5 unresolved); 648 nodes; 1,440 edges; 38 table nodes; 61 foreign-key edges.
- Graphify's only emitted warning was the duplicate `002_` migration prefix:
  - `002_final_registration_permissions.sql`
  - `002_registration_wizard.sql`
  Never run or reorder these blindly. Inspect the target database's migration history and schema first.
- Graphify is static analysis, not a runtime call trace. Five dynamic/unresolved JavaScript API calls remain for manual review. A missing edge is not proof that no dependency exists.
- The documentation-only commits after `46960cf` do not change application behavior; the CI results above are for the code baseline, not a new runtime deployment.
- PR #13 also contains an earlier Graphify audit implementation and a separate curated AI documentation set on another branch. Do not mix its older source baseline with PR #14 without comparing branches and resolving overlap deliberately.

## 5. Owner's local environment — latest reported state

- Local project: `/opt/lampp/htdocs/zayan_hardware`.
- Local branch at the last verified handoff: `local/audit-update-20261031`, based on `46960cf`.
- Local uncommitted/untracked files were reported:
  - modified `.htaccess` with `RewriteBase /zayan_hardware/`;
  - `.htaccess.before-rewrite-fix`;
  - untracked `update.sh`;
  - untracked `zayan_hardware_audit.sql`.
- Preserve these files. Inspect `git status` before every update. Do not reset, clean, overwrite, stage, or discard them without explicit approval.
- A pre-update full database backup was verified at `/home/a/backups/zayan-hardware/zayan_hardware_full_verified_20261031_023151.sql.gz`; SHA-256: `ec270536ba8bc1c6c30e777734988186a6ed98e134eac04b107ca0680154e276`.
- MariaDB system tables were repaired using `mysql_upgrade`; `mysql.proc` is now the expected 21 columns. Do not enable `event_scheduler`.
- Migrations 014 and 015 were applied locally and verified. Do not rerun them; 015 is not idempotent. Migration 013 already existed. Do not rerun migrations 001–012, especially either 002 migration.
- PHP syntax check passed for all project PHP files. Local Apache rewrite returned HTTP 200 at `http://localhost/zayan_hardware/public/`.
- Storage ACLs were set narrowly for Apache user `daemon`; do not replace them with broad permissions or `chmod 777`.

All local details above are a dated handoff. Recheck the actual machine before acting.

## 6. Confirmed content-retention behavior and remaining review

- `Issue::deleteIssue()` uses `deleted_at` (soft delete), not a physical row delete.
- `Issue::deleteReply()` and `Issue::deleteDiagnosticUpdate()` also use `deleted_at`; reply edit history is stored separately. One relationship-preservation concern remains: `deleteReply()` sets child replies' `parent_reply_id` to NULL before soft-deleting the parent. Child text survives, but its thread relationship is lost; review the UI and intended tombstone behavior before changing it.
- `ContentEditorService::pruneUnusedInlineMedia()` is intentionally a no-op so attachments referenced by previous body revisions are retained.
- The issue creation/edit/reply/diagnostic controller flows perform multiple database and filesystem steps. A database failure between steps can leave a partially completed operation (for example, a post/reply saved before inline-token resolution). This is a consistency/rollback review item, not evidence that existing posts were deleted. Add failure-injection tests before changing these flows.
- `GET /attachments/{id}` and issue audio require authentication; verify the intended visibility policy whenever issue privacy rules change. Personal-help attachment delivery additionally checks conversation participation; draft media checks draft ownership.

## 7. Non-negotiable content preservation

The owner's highest-priority requirement is that technical knowledge must not be lost.

- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or help-library content.
- Do not run account-retention/anonymization workers against real data. Dry-run and disposable-database integration tests only unless the owner explicitly approves otherwise.
- Account lifecycle must preserve stable user IDs and technical attribution; never replace anonymization with physical user deletion.
- Do not prune, bulk-delete, or migrate media without a verified inventory, backup, explicit retention policy, and rollback plan.
- Graphify is source-only and must not read or mutate member uploads, logs, backups, or database contents.

## 8. Engineering workflow

For each feature, trace the complete path:

`HTTP method + route → route parameters → controller → authorization/CSRF/rate limit → service → model/SQL → migration/table/FK → view/layout → JS/CSS/API → storage/logging/notifications → tests`.

Then:
1. inspect the current branch and exact source;
2. reproduce or prove the defect before editing;
3. make the smallest coherent fix;
4. add regression tests, including failure/rollback cases;
5. run PHP/JS lint, focused tests, Graphify, and relevant database integration;
6. review the complete diff and data-preservation impact;
7. update this file and the relevant architecture/feature documentation with verified facts only.

Never claim a task is complete merely because code was added or CI passed. Separate **implemented**, **tested**, **runtime-verified**, and **deployed** status.
