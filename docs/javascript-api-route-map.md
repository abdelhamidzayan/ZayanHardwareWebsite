# JavaScript API route map

This file manually traces browser `fetch()` calls to PHP routes and authorization owners. It complements Graphify because some URLs are built from a view-provided base path, draft ID, media UUID, or action form rather than a string literal inside the JavaScript source.

| JavaScript source | Browser request | PHP route | Controller / access |
|---|---|---|---|
| `post-composer.js` | `GET modelsUrl?q=...` | `GET /devices/models` | `DeviceCatalogController::models()`; authenticated member |
| `post-composer.js` | `POST saveUrl` | `POST /drafts/save` | `DraftController::save()`; owner session + CSRF |
| `post-composer.js` | `GET mediaBase/{draftId}/data` | `GET /drafts/{draftId}/data` | `DraftController::data()`; draft owner |
| `post-composer.js` | `POST mediaBase/{draftId}/media` | `POST /drafts/{draftId}/media` | `DraftController::upload()`; owner + CSRF + rate limit + revision |
| `post-composer.js` | `POST mediaBase/{draftId}/media/{uuid}/delete` | `POST /drafts/{draftId}/media/{mediaUuid}/delete` | `DraftController::deleteMedia()`; owner + CSRF + revision |
| `post-composer.js` | `POST mediaBase/{draftId}/media/{uuid}/rendered` | `POST /drafts/{draftId}/media/{mediaUuid}/rendered` | `DraftController::uploadRendered()`; owner + CSRF + rate limit + revision |
| `post-composer.js` | `POST mediaBase/{draftId}/media/{uuid}/editor` | `POST /drafts/{draftId}/media/{mediaUuid}/editor` | `DraftController::updateMediaEditor()`; owner + CSRF + revision |
| `post-composer.js` | `POST suggestUrl` | `POST /devices/suggestions` | `DeviceCatalogController::suggest()`; authenticated + CSRF + rate limit |
| `register.js` | `GET endpoint?names[]=...` | `GET /register/usernames` | `AuthController::usernames()`; public username availability + IP rate limit |
| `drafts.js` | `POST form.action` | `POST /drafts/{draftId}/delete` | `DraftController::delete()`; draft owner + CSRF |
| `notifications.js` | `GET base/push/public-key` | `GET /push/public-key` | `PushController::publicKey()`; public key only |
| `notifications.js` | `POST base + path`, call site `/push/subscribe` | `POST /push/subscribe` | `PushController::subscribe()`; authenticated + CSRF + rate limit + endpoint host/port allow-list |
| `notifications.js` | `POST base + path`, call site `/push/unsubscribe` | `POST /push/unsubscribe` | `PushController::unsubscribe()`; authenticated + CSRF + rate limit + owner-scoped delete |

## Important URL construction details

- The authenticated app shell supplies `body.dataset.pwaBase` from `Request::basePath()`. This is the deployment subdirectory prefix; it is not a complete route.
- The post composer view supplies `data-save-url`, `data-models-url`, `data-suggest-url`, and `data-media-base`. The media suffixes are defined by the route table.
- Draft IDs and media UUIDs are URI-encoded in JavaScript before being appended to the media base. The backend still validates ownership and revision; URL encoding is not authorization.
- `notifications.js` centralizes POST in `post(path, values)`; its two call sites are literal and map to `/push/subscribe` and `/push/unsubscribe`.
- `drafts.js` uses the HTML form's `action`, which is generated server-side as `/drafts/{id}/delete`.

## Graphify unresolved-call reconciliation

The Phase 2 Graphify artifact for commit `31356d86c1566d470fac267cc585f7f8ec560dfe` reported 15 API calls: 10 linked automatically and 5 retained as dynamic. All five were manually traced:

| Graphify source node | Expression | Manual resolution |
|---|---|---|
| `public/js/notifications.js:25` | `base + path` (POST) | `post()` has two literal call sites: `/push/subscribe` → `POST /push/subscribe`, and `/push/unsubscribe` → `POST /push/unsubscribe`. Both are private, authenticated, CSRF-protected and rate-limited; unsubscribe is owner-scoped. |
| `public/js/notifications.js:60` | `base + '/push/public-key'` (GET) | `GET /push/public-key`; returns the public VAPID key only. The base prefix comes from `body.dataset.pwaBase`. |
| `public/js/register.js:306` | `endpoint + '?' + params` (GET) | The endpoint is derived at line 37 from the registration form action by replacing the final `/register` segment with `/register/usernames`; maps to public `GET /register/usernames`, protected by an IP rate limit. |
| `public/service-worker.js:14` | `request` (GET) | Generic same-origin navigation request in the service-worker `fetch` event. It is network-first and falls back to `offline.html`; it is not a fixed PHP API endpoint. |
| `public/service-worker.js:16` | `request` (GET) | Generic same-origin static asset request under `/css/`, `/js/`, or `/assets/` with an allow-listed static extension. It uses the static cache and is not a PHP API endpoint. |

The five remaining nodes therefore represent three manually resolved PHP endpoints (including the two literal Push POST call sites) and two generic Service Worker request pass-throughs. They are not missing backend routes. The scanner intentionally leaves them dynamic rather than guessing a single URL.

## Review result

All 15 browser/service-worker fetch sites in this Graphify artifact are now accounted for: 10 linked by the scanner and 5 manually reconciled above. The route mapping does not replace runtime authorization tests; every server-side POST route is separately covered by `tests/php/post-route-csrf-policy.php`.

Re-run the Graphify scanner and update this map when a route, JS endpoint, form action, data attribute, or Service Worker strategy changes.
