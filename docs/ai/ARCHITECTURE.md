# Architecture Guide

## Request lifecycle
1. The web server serves the application through `public/index.php`.
2. Bootstrap loads the application and calls `App::boot()`.
3. `App` loads configuration/environment values, sets UTC/error handling/request ID/session and log retention.
4. `config/routes.php` registers HTTP routes.
5. `Router::dispatch()` checks guest access, matches method/path, normalizes declared route parameters and invokes a controller.
6. Controllers coordinate Core classes, Services and Models.
7. Models use the shared PDO connection to read/write MySQL.
8. Controllers render PHP templates through `View`; layouts load shared CSS/JS and feature assets.
9. Responses are sent with HTTP status/headers. Logging and notification services are invoked by relevant flows.

Key source: `public/index.php`, `app/core/App.php`, `app/core/Router.php`, `app/core/Controller.php`, `Request.php`, `Response.php`, `View.php`, and `config/routes.php`.

## MVC boundaries
| Layer | Responsibility | Location |
|---|---|---|
| Entry point | Bootstrap and request dispatch | `public/index.php` |
| Core | Router, Request/Response, rendering, validation, CSRF and base classes | `app/core/` |
| Configuration | Environment-derived configuration and route registration | `config/` |
| Controllers | HTTP input, orchestration, authorization and response | `app/controllers/` |
| Services | Reusable domain logic and external integrations | `app/services/` |
| Models | Persistence/domain queries and writes | `app/models/` |
| Views | Templates, layouts and partials | `app/views/` |
| Frontend | CSS, JS, manifest, service worker and icons | `public/` |
| Schema history | SQL migration scripts | `database/migrations/` |
| Runtime/private data | Logs, uploads and cache | `storage/` |

## Routing and type contract
- Routes are explicitly registered in `config/routes.php`.
- Router invokes controller methods using named argument unpacking, so each `{placeholder}` must exactly match a controller parameter name.
- `Router::INTEGER_ROUTE_PARAMETERS` defines the integer placeholders, currently `id` and `userId`. These match numeric-only segments and are cast to `int`.
- UUID and text placeholders such as `draftId`, `mediaUuid`, `replyId`, `updateId` and `username` remain strings.
- A route signature/type consistency check is part of the Graphify audit.
- Guest access is checked before route dispatch. New public routes require deliberate review of the guest allow-list.
- Mutations should use POST, CSRF checks, validation and object-level authorization.

## Database
- Connection: `config/database.php` and `app/models/Database.php`.
- Schema evolution: `database/migrations/`.
- Migration files do not prove migration execution. Test on a disposable MySQL/MariaDB database before deployment.
- Migration sequence currently includes two files with prefix `002_`; do not rename/reorder without understanding the migration runner and installed environments.

## Frontend and PWA
- Shared tokens: `public/css/tokens.css`.
- Shared styles include `app.css`, `components.css`, `pages.css` and `responsive.css`.
- Reuse established components/tokens, CSS logical properties and RTL patterns.
- Keep authenticated HTML, private media, drafts, user-specific API responses and other private data out of the service-worker cache.
- Web Push VAPID private keys stay only in server-side environment configuration.

## Private media and logs
- Logs are implemented in `app/core/App.php` and documented under `storage/logs/`.
- Private files belong under `storage/private/` and must be delivered through authenticated/authorized routes.
- Check MIME by server-side inspection, file size, path canonicalization, ownership and participant permissions before serving uploads.

## Feature tracing
Route → controller signature → service/model → schema tables/migrations → view/layout → JS/CSS → authorization/CSRF → storage/logging/notifications. Follow existing feature patterns and update this guide when boundaries change.

## Evidence boundary
This guide is source-derived from the audit source baseline `1752f1b4c2acb48c5fbad182a0a3e82cace23c48` and the audit fix at `f455dcab3818c5d19a9aa2487929e937f8636b14`. It is not a runtime/deployment certification.
