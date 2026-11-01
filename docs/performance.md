# Performance Guide — Zayan Hardware

> How the site is kept fast, what the application already does, and what the
> web server must provide. Last updated: 2026-11-01.

## 1. What the application does

### Versioned static assets

Every stylesheet and script the app links is served through
`App\Core\Assets::url()` (exposed to views as `$asset()`), which appends
`?v=<file-mtime>` to the URL:

- a deployed change to any CSS/JS file produces a **new URL**, so browsers
  fetch the fresh file immediately;
- an unchanged file keeps its URL, so browsers reuse their cached copy and
  skip the download entirely.

This is what makes the long-lived cache policy in `public/.htaccess` safe.

### Apache caching and compression (`public/.htaccess`)

- `mod_deflate` compresses HTML, CSS, JS, JSON, the web manifest, and SVG.
  Binary media (JPEG/PNG/WEBP) is not recompressed; range responses are never
  compressed.
- `mod_expires` + `mod_headers` send `Cache-Control: public,
  max-age=31536000, immutable` for versioned CSS/JS and one month for
  images/icons. `service-worker.js` is explicitly excluded and always sent
  with `no-cache, no-store, must-revalidate` so PWA updates roll out at once.
- Static files get `X-Content-Type-Options: nosniff`.

### Avatar conditional responses

`AvatarController::show()` streams avatars through a helper that sends
`ETag` and `Last-Modified` validators and answers matching
`If-None-Match` requests with a lightweight `304 Not Modified` instead of
re-streaming the image. The avatar URL does not change when a member updates
their picture, so caching stays revalidation-based: a new upload changes the
file mtime, the ETag changes, and browsers immediately receive the new image.

### Already in place

- All application images are rendered with `loading="lazy"`.
- The service worker serves static assets network-first with an offline
  cache fallback; it never caches HTML, account data, or uploads.
- The Composer autoloader is authoritative-optimized
  (`"optimize-autoloader": true`); run `composer install --no-dev --optimize-autoloader`
  when deploying.
- Hot header queries (notifications, help requests) run against indexed
  columns (`idx_notifications_user_read_created`,
  `idx_help_requests_requester_status`, `idx_help_requests_helper_status`,
  `idx_help_messages_unread`). No schema change was needed.

## 2. What the web server must provide

These are host-level settings; the repository cannot enable them by itself.

### Required Apache modules

The `.htaccess` directives are wrapped in `<IfModule>` guards, so nothing
breaks without them — but the speed benefits only apply when the modules are
enabled:

```bash
sudo a2enmod deflate expires headers
sudo systemctl reload apache2
```

### PHP OPcache (largest single server-side win)

Without OPcache, PHP re-reads and re-compiles every source file on every
request. Enable it in `php.ini` (or `/etc/php/8.2/apache2/conf.d/`):

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

With `validate_timestamps=1` and `revalidate_freq=0`, code changes are picked
up immediately while compiled files stay cached. Check with
`php -v` (should mention Zend OPcache) or a `phpinfo()` page on staging only.

### HTTP/2 (or HTTP/3)

Multiple CSS/JS files load far faster over HTTP/2 because they share one
connection. Enable `mod_http2` and serve the site over HTTPS (HTTP/2 requires
TLS in browsers).

### Verify after deployment

```bash
# Versioned asset: expect Cache-Control: max-age=31536000, immutable
curl -sI "https://<site>/css/app.css" | grep -i cache-control

# Compression: expect Content-Encoding: gzip (or br)
curl -sI -H "Accept-Encoding: gzip" "https://<site>/css/app.css" | grep -i content-encoding

# Service worker: expect no-store
curl -sI "https://<site>/service-worker.js" | grep -i cache-control

# Avatar revalidation: second request with the returned ETag must be 304
curl -sI "https://<site>/avatar/1" | grep -i etag
curl -sI -H 'If-None-Match: "<etag-from-above>"' "https://<site>/avatar/1" | head -1
```

## 3. Regression coverage

`tests/php/asset-versioning.php` (wired into the PHP syntax/security
workflow) verifies:

- existing public assets receive a modification-time `?v=` marker;
- changed files produce changed URLs;
- missing or traversal paths never receive a version marker;
- avatar responses carry ETag/Last-Modified and answer exact, weak (`W/`),
  and wildcard (`*`) conditional requests with an empty-body `304`, while a
  stale ETag receives the fresh `200` image.
