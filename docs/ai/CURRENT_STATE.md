# Current Project State

Snapshot date: 2026-10-30. Verify Git and runtime again before acting.

## Repository and branch
- Repository: `abdelhamidzayan/ZayanHardwareWebsite`.
- Product source baseline: `feature/profiles-help-media` at `1752f1b4c2acb48c5fbad182a0a3e82cace23c48`.
- Audit branch: `audit/full-system-20261030`.
- Latest audit commit: `f455dcab3818c5d19a9aa2487929e937f8636b14`.
- The audit branch starts from the feature branch baseline. It is not merged into `feature/profiles-help-media` or `main`, and it is not deployed.
- The older `audit/graphify-system-20261030` branch starts from `main` and is 226 commits behind the feature baseline; do not merge it into the feature branch.

## Completed on the audit branch
- Corrected Router handling for the integer `userId` placeholder used by `GET /avatar/{userId}`.
- Kept text/UUID route values as strings.
- Added `tests/router-route-parameters.php` to check numeric matching and type normalization.
- Added a Graphify code-relationship audit script and a GitHub Actions workflow for this branch.
- Hardened static consistency checks so route/signature mismatches and extraction failures make the audit job fail.
- Added this curated project-memory set and a source-derived graph.

## Verification status
- Verified by GitHub source inspection: route definitions and target controller signatures, including all explicitly declared routes containing placeholders.
- Not executed in this chat: PHP CLI test, full PHP lint, JavaScript syntax checks, database integration tests, browser/mobile tests, Graphify extraction or deployment smoke tests.
- The Graphify workflow is configured to run on pushes affecting the code/audit paths on this branch. Its execution result and generated artifact have not yet been verified.
- The Markdown graph is manually curated from source. Do not call it Graphify output.

## Product and architecture snapshot
- Arabic-first RTL community/knowledge platform specialized in mobile hardware repairs, board diagnosis, measurements and schematics.
- Custom PHP MVC, MySQL, vanilla JavaScript/CSS, PWA and Web Push.
- Feature areas: authentication/accounts, technical posts/issues, replies and diagnostic updates, profiles/avatars, private help requests/public help library, drafts/composer, device catalog, notifications/push, skill tests and admin.
- Routes: `config/routes.php`; Router: `app/core/Router.php`.
- Migrations exist through `012_post_composer_drafts_and_device_catalog.sql`; filenames do not prove they have been applied.

## User-reported local checkout — preserve
Previously reported local path: `/opt/lampp/htdocs/zayan_hardware`.
Previously reported local branch: `local/zayan-hardware-profiles-help-media-5124577`.
Previously reported local modified file: `.htaccess`, with local RewriteBase `/zayan_hardware/`.
Previously reported untracked files: `update.sh` and `zayan_hardware_audit.sql`.
These are user-reported, not verified by GitHub. Before any local update, inspect `git status`; do not overwrite, stage, delete or discard them.

## User-reported logging/deployment details — not independently verified
- Daily logs under `storage/logs/`: app, access, security and audit channels.
- 30-day retention, outside public root; Apache access was configured with ACL.
- Production should keep `APP_DEBUG=false`.
- The exact live deployment commit, server config and migration state remain unverified.

## Remaining work
1. Verify GitHub Actions run for `f455dcab` and retrieve the Graphify artifact.
2. Review Graphify extraction/health output and compare it with the curated graph.
3. Continue feature-by-feature logic and security review, especially authorization, private media, migrations and browser/PWA behavior.
4. Resolve the duplicate `002_` migration prefix only after inspecting the migration process and every affected environment.
5. Do not merge or deploy the audit branch until the owner reviews it and required tests pass.
