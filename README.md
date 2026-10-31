# Zayan Hardware (Hardware Tech)

PHP 8.2+ MVC + MySQL. Document root must be `public/`.

## Start here — project architecture and AI handoff

**Working on this repository? Read these first:**

1. **[AI_CONTEXT.md](AI_CONTEXT.md)** — durable project handoff, current verified branch/CI state, preservation rules, and the required workflow for future AI sessions.
2. **[Architecture and dependency map](docs/architecture-code-map.md)** — how requests flow through routes, controllers, services, models, database, views, and frontend.
3. **[Graphify guide](docs/graphify.md)** — how to generate and review the machine-readable code relationship graph.
4. **[Contributor rules](AGENTS.md)** — mandatory safety, architecture, and Git rules.
5. **[GitHub Actions and usage-budget policy](AGENTS.md#github-actions-and-usage-budget)** — CI requirements, quota exhaustion, and zero-overage safeguards.
6. [Security hardening](docs/security-hardening.md) · [Account retention policy](docs/account-retention.md) · [Post composer and drafts](docs/post-composer-and-drafts.md) · [Profiles and personal help](docs/profiles-personal-help.md)

The architecture map is the first technical reference. Always verify the active branch, commit, runtime, and database before changing code. Documentation and CI do not prove that an unmerged branch is deployed.

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
