# POST Route Security Matrix

This matrix is generated from `config/routes.php` at the Phase 2 security review. Every declared POST route must pass the automated `tests/php/post-route-csrf-policy.php` check.

## Enforcement rules

- Router routes are private by default. Only `postPublic()` is guest-accessible.
- The public POST allow-list is deliberately limited to `/login` and `/register`.
- Every POST handler must call `Csrf::verify()`, or explicitly delegate to a same-controller handler that does.
- Authentication is not a substitute for object authorization. Content, draft, personal-help, admin, and private-media operations must still validate ownership, participant status, state transitions, and model/service invariants at the write boundary.
- The static test establishes CSRF coverage and public-route allow-list only. It does not prove all authorization rules; the focused database integration tests and manual review remain necessary.

## Current route inventory

| Method | Route | Handler | Access | CSRF |
|---|---|---|---|---|
| POST | `/settings/help-availability` | `PageController::saveHelpAvailability()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/create` | `IssueController::create()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/save` | `DraftController::save()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/{draftId}/media` | `DraftController::upload()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/{draftId}/media/{mediaUuid}/rendered` | `DraftController::uploadRendered()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/{draftId}/media/{mediaUuid}/editor` | `DraftController::updateMediaEditor()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/{draftId}/media/{mediaUuid}/delete` | `DraftController::deleteMedia()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/drafts/{draftId}/delete` | `DraftController::delete()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/devices/suggestions` | `DeviceCatalogController::suggest()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/device-model-suggestions/{id}` | `DeviceCatalogController::moderate()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/reply` | `IssueController::reply()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/replies/{replyId}/edit` | `IssueController::editReply()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/replies/{replyId}/delete` | `IssueController::deleteReply()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/diagnostic-updates` | `IssueController::diagnosticUpdate()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/diagnostic-updates/{updateId}/edit` | `IssueController::editDiagnosticUpdate()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/diagnostic-updates/{updateId}/delete` | `IssueController::deleteDiagnosticUpdate()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/confirm-repair` | `IssueController::confirmRepair()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/reject-repair` | `IssueController::rejectRepairConfirmation()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/edit` | `IssueController::edit()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/delete` | `IssueController::deleteIssue()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/vote` | `IssueController::vote()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/accept` | `IssueController::accept()` | Authenticated (Router default) | Delegated to CSRF-guarded method |
| POST | `/issues/{id}/close` | `IssueController::close()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/reopen` | `IssueController::reopen()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/issues/{id}/review-result` | `IssueController::reviewResult()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/notifications/read-all` | `NotificationController::readAll()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/notifications/{id}/open` | `NotificationController::open()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/push/subscribe` | `PushController::subscribe()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/push/unsubscribe` | `PushController::unsubscribe()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/login` | `AuthController::login()` | Guest allow-listed | Direct or delegate checked by CI |
| POST | `/register` | `AuthController::register()` | Guest allow-listed | Direct or delegate checked by CI |
| POST | `/logout` | `AuthController::logout()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/account/deletion-request` | `AccountController::requestDeletion()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/skill-tests` | `AdminSkillTestController::create()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/skill-tests/{id}/status` | `AdminSkillTestController::setStatus()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/skills/tests/{id}/submit` | `SkillTestController::submit()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/update` | `AdminUpdateController::run()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/featured-posts/{id}` | `AdminFeaturedPostsController::update()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/users/{id}/password-reset` | `AdminUserController::resetPassword()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/users/{id}/status` | `AdminUserController::setStatus()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/admin/users/{id}/role` | `AdminUserController::setRole()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/profile/avatar` | `AvatarController::update()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/help-requests/create` | `HelpRequestController::create()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/help-requests/{id}/accept` | `HelpRequestController::accept()` | Authenticated (Router default) | Delegated to CSRF-guarded method |
| POST | `/help-requests/{id}/decline` | `HelpRequestController::decline()` | Authenticated (Router default) | Delegated to CSRF-guarded method |
| POST | `/help-requests/{id}/messages` | `HelpRequestController::send()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/help-requests/{id}/close` | `HelpRequestController::close()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/help-requests/{id}/publication` | `HelpRequestController::proposePublication()` | Authenticated (Router default) | Direct or delegate checked by CI |
| POST | `/help-publications/{id}/respond` | `HelpRequestController::respondPublication()` | Authenticated (Router default) | Direct or delegate checked by CI |

## Focused authorization boundaries already covered

| Boundary | Enforcing layer | Regression coverage |
|---|---|---|
| Router private-by-default access | `app/core/Router.php` | `tests/php/router-public-routes.php` |
| Technical content ownership, reply and diagnosis invariants | Controllers plus `Issue` / `ContentWriteService` | `tests/php/issue-authorization-integration.php`, `tests/php/content-write-integration.php`, `tests/php/reply-tombstone-integration.php` |
| Draft ownership, revision lock, and media path containment | `DraftController`, `PostDraft`, `DraftPublishingService` | `tests/php/draft-publish-integration.php`, `tests/php/draft-media-recovery-integration.php` |
| Personal-help participant and attachment ownership | `HelpRequestController`, `HelpRequest`, `HelpRequestMediaService` | `tests/php/help-request-media-integration.php` |
| Active administrator and last-admin protections | `AdminRosterService` and the relevant models | `tests/php/admin-roster-integration.php`, `tests/php/device-catalog-integration.php` |
| Rate limits for authenticated and public actions | `RateLimiter` | `tests/php/rate-limiter-integration.php` |
| Password reset invalidates older sessions | `users.auth_version`, `AuthService`, `AdminUserController` | `tests/php/auth-session-version-integration.php` |

## Maintenance

The CI policy test parses all `post()` and `postPublic()` declarations from the route table. Additions, renamed handler methods, or delegation changes that omit CSRF verification cause the PHP workflow to fail. Update this document whenever the route inventory or its authorization boundary changes.
