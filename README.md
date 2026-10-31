# Zayan Hardware (Hardware Tech)

PHP 8.2+ MVC + MySQL. Document root must be `public/`.

## Start here — project architecture and AI handoff

**Working on this repository? Read these first:**

1. **[Contributor rules](AGENTS.md)** — mandatory safety, architecture, documentation, and Git rules.
2. **[AI_CONTEXT.md](AI_CONTEXT.md)** — durable project handoff, verified historical context, and important implementation constraints.
3. **[Current project state](docs/CURRENT_STATE.md)** — authoritative `main` baseline, known verification caveats, and next-step workflow.
4. **[Update log](UPDATES.md)** — chronological record of implemented changes and their verification state.
5. **[Architecture and dependency map](docs/architecture-code-map.md)** — how requests flow through routes, controllers, services, models, database, views, and frontend.
6. **[AI start prompt](docs/AI_START_PROMPT.md)** — short reusable prompt for opening a new AI conversation.
7. [Three-phase completion plan](docs/COMPLETION_PLAN.md) — phase scope, acceptance criteria, and remaining release gates.
8. [POST route security matrix](docs/SECURITY_POST_ROUTE_MATRIX.md) — CSRF, guest access, and authorization review for every POST endpoint.
9. [Database backup and restore runbook](docs/DATABASE_BACKUP_RESTORE.md) — safe separate-database restore and read-only schema/row-count comparison.
10. [Storage recovery triage](docs/STORAGE_RECOVERY_RUNBOOK.md) — safe handling of pending filesystem recovery records.
11. [Graphify guide](docs/graphify.md) · [Security hardening](docs/security-hardening.md) · [Account retention policy](docs/account-retention.md) · [Post composer and drafts](docs/post-composer-and-drafts.md) · [Profiles and personal help](docs/profiles-personal-help.md)

The documentation is a map, not a substitute for source inspection. Always verify the active branch, commit, local changes, runtime, and database before changing code. GitHub branches, local checkouts, CI results, and deployments are separate states.

## GitHub Actions and CI budget

GitHub Actions is part of the project's quality gate. Keep workflow files and their test matrix version-controlled; do not delete or permanently disable CI to work around exhausted minutes. When the included quota is exhausted, stop reruns and new dispatches until the reset date or explicit authorization for paid usage. Verify the Actions spending budget first; use a **$0 budget** where supported to prevent accidental overage. A run with no executed steps is neither a passing nor a failing application test. The full rules live in [AGENTS.md](AGENTS.md#github-actions-and-usage-budget).

## Run locally
1. Copy `.env.example` to `.env` and edit it.
2. (Optional) `composer install` — without it `public/index.php` uses a built-in autoloader.
3. Serve `public/`:
   - Quick: `php -S 127.0.0.1:8080 -t public`
   - XAMPP: open `http://localhost/zayan-hardware/public/`
4. Open `/feed`. Unknown URLs must show the 404 page.

## Logging and diagnostics

Application logs are written outside the public web root under `storage/logs/` as daily JSON Lines files:

- `app-YYYY-MM-DD.log`: PHP warnings, uncaught exceptions, and fatal errors.
- `access-YYYY-MM-DD.log`: completed HTTP requests, response status, duration, route, user ID, and request ID.
- `security-YYYY-MM-DD.log`: failed/successful sign-ins, rate limits, blocked accounts, and rejected CSRF attempts. These records include the client IP for abuse investigation.
- `audit-YYYY-MM-DD.log`: important account events such as registration and logout.

The default retention period is 30 days. Set `LOG_RETENTION_DAYS` in `.env` to change it. Log timestamps use UTC. Never move these files into `public/` or expose them through a web route. Passwords, session IDs, CSRF tokens, and secret-like context fields are redacted.

To follow today's logs from the project root:

```bash
tail -f storage/logs/app-$(date -u +%F).log
tail -f storage/logs/access-$(date -u +%F).log
tail -f storage/logs/security-$(date -u +%F).log
```

In production, set `APP_DEBUG=false` so visitors never receive internal exception details.
