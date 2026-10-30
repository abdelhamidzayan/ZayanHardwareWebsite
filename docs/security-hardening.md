# Security hardening: rate limits, media delivery, and account retention

## Rate limits

Migration 014 creates the persistent `rate_limit_buckets` table. `RateLimiter::allowForUser()` atomically checks and increments both an account bucket and a hashed source-IP bucket in one transaction. Controller checks run before the associated write or file move.

Current limits use fixed windows:

| Action | Account limit | Window | Source IP limit |
|---|---:|---:|---:|
| New technical post | 5 | 10 minutes | 25 |
| Diagnostic update | 8 | 10 minutes | 30 |
| Reply / comment | 15 | 5 minutes | 60 |
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

## Account deletion: worker intentionally not added yet

The current account-deletion endpoint disables the account and records a purge date 30 days in the future, but the repository does not yet contain a scheduled purge worker. A generic hard `DELETE users` is unsafe: schema deliberately has `ON DELETE RESTRICT` relations for authored issues, replies, attachments, edit histories, repair-result submissions, reputation events, terms acceptance, and administration audit/reset records. A safe implementation must first define whether authored technical knowledge is removed or retained under an anonymized author, then cover private files, audit records, personal-help conversations, profile data, and cancellation races in one reviewed retention policy. No account or production data is deleted by these changes.
