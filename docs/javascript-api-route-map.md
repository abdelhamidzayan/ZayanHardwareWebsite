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

## Review result

All current JavaScript API call sites inspected in the tracked JS files have a corresponding route or server-generated form action. Graphify still reports five unresolved dynamic API expressions because its static matcher does not fully expand every base-path/data-attribute/action concatenation. Those five are manually mapped above; unresolved Graphify edges are not evidence that the backend route is absent.

Re-run the Graphify scanner and update this map when a route, JS endpoint, form action, or data attribute changes.
