# Security hardening: rate limits, media delivery, and account retention

## Rate limits

Migration 014 creates the persistent `rate_limit_buckets` table. Apply migration 014 to the target database before deploying the code that calls this limiter; the application does not run it automatically. `RateLimiter::allowForUser()` atomically checks and increments both an account bucket and a hashed source-IP bucket in one transaction. Controller checks run before the associated write or file move.

Current limits use fixed windows:

| Action | Account limit | Window | Source IP limit |
|---|---:|---:|---:|
| New technical post | 5 | 10 minutes | 25 |
| Diagnostic update | 8 | 10 minutes | 30 |
| Reply / comment | 15 | 5 minutes | 60 |
| Edit a post | 10 | 10 minutes | 30 |
| Personal help request | 3 | 1 hour | 15 |
| Personal help message | 24 | 5 minutes | 100 |
| Device model suggestion | 5 | 1 hour | 25 |
| Draft media upload | 12 | 10 minutes | 50 |
| Rendered image upload | 20 | 10 minutes | 80 |
| Avatar upload | 6 | 1 hour | 24 |

The IP bucket is hashed before storage. Old buckets are pruned opportunistically; a separate cleanup cron is not required.

## File delivery

`Response::streamFile()` streams verified files in chunks of at most 1 MiB. Issue attachments and draft media retain byte-range responses, allowing video/audio seeking without loading the complete file into PHP memory. Personal-help attachments and avatars use the same bounded-memory path.

Incoming uploads already use PHP temporary upload files and `move_uploaded_file()`; each upload service validates the temporary file before moving it into private storage. Avatar image re-encoding still requires the image library to decode the bounded 5 MiB source image.


## Serialized administrative account transitions

The admin site updater now acquires the same MySQL advisory lock used by account status/role changes, deletion requests, and retention anonymization: `zayan_hardware_admin_deletion_request`. It holds that connection-level lock from the database recheck until the update command returns, and releases it in a `finally` block. The lock is acquired before the active-admin row check; lock acquisition failure aborts the update. This prevents a concurrent suspension, role demotion, deletion request, or anonymization from racing with the system update.

The regression tests require the updater and account lifecycle write paths to acquire and release the same lock. The updater script and sudoers configuration remain host-level controls requiring local verification.

## Frontend output / DOM sink review

The reviewed JavaScript files contain three `innerHTML` assignments:
- `app.js` and `register.js` assign only fixed SVG icon fragments selected by a boolean mode; no user-provided text is inserted.
- `pwa-guide.js` constructs the install guide from fixed platform-specific labels, instructions, and steps selected from browser feature detection; those values do not come from account or post content.

The reviewed issue, help-request, profile, authentication, and admin templates use the shared `$e` HTML-escaping helper for dynamic text and attributes. `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP` protects the server-generated initial composer JSON embedded in a script context. Raw `$content` in the application layout is a captured server-side template render, not user content passed directly to HTML.

Re-run this review when a new dynamic DOM sink, HTML renderer, or inline script payload is introduced. Prefer `textContent` and DOM node construction for any content derived from users or external services.

## Authentication session revocation

Migration `019_session_version_invalidation.sql` adds `users.auth_version`. A successful login stores the current version in the session; authenticated requests compare the session value with the current database value and clear the session when they differ.

Increment `auth_version` in the same transaction as every security-sensitive account transition:
- administrator password reset;
- account suspension, ban, or reactivation;
- role change (promotion or demotion);
- member deletion request (`deletion_pending`);
- final identity anonymization.

This prevents a cookie issued before a security transition from becoming valid again after the account is reactivated. Login completion also carries the version checked with the password, and the password rehash update is conditional on that version. A slow login started with the old password therefore cannot overwrite a newer administrative password reset.

Existing sessions without `auth_version` require a fresh sign-in after migration 019 and the corresponding application code are deployed together. Apply the schema before deploying code that reads the new column. The migration is included in the clean-install path; it has not been applied to the owner's local database in this Phase 2 work.

Regression coverage:
- `tests/php/auth-session-version-integration.php`: password-reset revocation, stale login/re-hash race, suspension, and reactivation with an old cookie.
- `tests/php/account-session-revocation-policy.php`: static guard over all account-state/role/anonymization write paths.
- `tests/php/login-information-disclosure.php`: same public response for invalid and suspended accounts while retaining distinct internal security events.

## Administrative site-update execution

The in-app update action is protected by an authenticated active-admin check, CSRF validation, a per-admin rate limit, and an active-admin database row-lock recheck immediately before executing a fixed argument array. The command path and working directory are constants; request values are never appended as shell arguments. Start/completion/failure events are recorded in audit/application logs.

The configured script itself (`/home/a/bin/zayan-hardware-update.sh`) and the machine's sudoers rule are outside the Git repository. Review and verify those two local operating-system controls separately before enabling the action on a live host. The repository regression test verifies the in-app guardrails, not the contents or permissions of the external script.

## Account deletion and technical-content retention

The repository contains an account-retention service designed to anonymize account identity while preserving technical history, but **there is no approved CLI entry point, scheduler, or automatic worker** in the release candidate. The earlier `bin/anonymize-deleted-accounts.php` command was intentionally removed. A 30-day deletion deadline therefore does not mean the account has been anonymized; pending requests remain `deletion_pending` until a separately reviewed and approved execution mechanism exists.

The intended service behavior is to retain the stable user ID and all technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, help-library content, private-help messages, notifications, and drafts. It must not physically delete technical rows or files. Do not add a cron job or invoke the service through an ad-hoc runner. See `docs/account-retention.md`.


## Administrator diagnostic console and privileged host access

The browser diagnostic console accepts bounded batches of at most eight independent allowlisted commands. Every line is dispatched internally; request text is never interpreted by a shell. The endpoint retains CSRF validation, per-admin rate limiting, active-admin revalidation, audit events, output bounds, path restrictions, and secret redaction. It does not provide arbitrary command execution, file mutation, package installation, `sudo`, or `su`.

Privileged host operations must use a direct SSH TTY over the trusted Tailscale network. The sudo password is entered into the SSH client's interactive prompt and must never transit the PHP application. See [Admin terminal and SSH operations](ADMIN_TERMINAL_AND_SSH.md).
