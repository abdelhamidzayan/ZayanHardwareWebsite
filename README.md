# Zayan Hardware (Hardware Tech)

PHP 8.2+ MVC + MySQL. Document root must be `public/`.

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
