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

## Account deletion and technical-content retention

The account-retention worker now completes the 30-day request by **anonymizing the account identity**, not by deleting the user row. The user ID stays unchanged so every foreign key and attribution link remains valid. Login is disabled, the username is replaced with a generated anonymous handle, the display name becomes `عضو محذوف`, email/phone and profile biography/workshop data are cleared, and push subscription credentials are neutralized.

**No database rows are deleted by the worker.** In particular, all published technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation records, published help-library content, private-help messages, notifications, and drafts remain in place. Published help-library author snapshots are changed to the anonymous display name, while the technical text remains untouched. Terms acceptance and security audit records remain, but stored IP hashes and free-form admin reset notes are cleared.

The worker defaults to dry-run. Use `php bin/anonymize-deleted-accounts.php` to inspect due requests and add `--execute` only when the output has been reviewed. See `docs/account-retention.md` for the exact policy and scheduler setup. It has not been run against a production database.
