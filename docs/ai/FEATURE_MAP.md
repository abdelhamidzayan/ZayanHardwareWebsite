# Feature Map

Index of the system's current source areas. Always verify exact call chains at the active commit.

| Feature | Routes / entry | Controllers | Models / services | Views / frontend / schema |
|---|---|---|---|---|
| Application shell | `/`, `/help`, `/policies`, `/settings` | HomeController, PageController | Core/config | home, pages, layouts |
| Authentication | `/login`, `/register`, `/logout`, usernames | AuthController, AccountController | User, AuthService, PhoneNumber, RateLimiter | auth views, register.js; migrations 001–002 |
| Feed and technical issues | `/feed`, `/issues/create`, `/issues/{id}` | FeedController, IssueController, MyPostsController | Issue, ContentEditorService | feed/issues/my-posts views, editor scripts; migrations 001–010 |
| Replies and diagnostics | reply/edit/delete, diagnostic updates, votes, repair confirmation/close/reopen | IssueController | Issue | issue detail/discussion views and assets; migrations 005, 007, 010 |
| Attachments/audio | `/attachments/{id}`, issue audio routes | IssueController | Issue, ContentEditorService | issue media views/scripts; migration 008 and related fields |
| Profiles and avatars | `/profile/{username}`, `/profile/avatar`, `/avatar/{userId}` | ProfileController, AvatarController | User, AvatarService | profile view, avatar crop script; migration 011 |
| Personal help and public library | `/help-requests/*`, `/help-library/*` | HelpRequestController | HelpRequest, HelpRequestMediaService, NotificationService | help request/library views and JS; migration 011 |
| Composer and drafts | `/drafts/*`, `/issues/create` | DraftController, IssueController | PostDraft, DraftPublishingService, Issue | content editor, drafts/composer scripts; migration 012 |
| Device catalog | `/devices/*`, admin suggestions | DeviceCatalogController | DeviceCatalog | composer/admin views; migration 012 |
| Notifications and push | `/notifications/*`, `/push/*` | NotificationController, PushController | Notification, PushSubscription, NotificationService, WebPushService | notifications views/JS, service worker; migration 006 |
| Skill tests | `/skills/tests/*`, `/admin/skill-tests` | SkillTestController, AdminSkillTestController | SkillTestService | skills/admin views |
| Administration | `/admin`, users, featured posts, update | AdminFeaturedPostsController, AdminUserController, AdminUpdateController | Feature models and User | admin views and featured-card partial |
| Workbench / PWA | `/workbench`, manifest/service worker/offline | WorkbenchController, PushController | Verify exact implementation per change | workbench view, manifest, service worker, offline page |

## Route parameter type map
- Integer: `id`, `userId` (declared in `Router::INTEGER_ROUTE_PARAMETERS`).
- String/UUID: `draftId`, `mediaUuid`, `replyId`, `updateId`, `username`.
- Check both route placeholder and controller signature before adding or renaming either.

## Migration index — filenames only
- `001_issue_discussion.sql`
- `002_final_registration_permissions.sql`
- `002_registration_wizard.sql`
- `003_featured_home_issues.sql`
- `004_content_types.sql`
- `005_diagnostic_updates_and_repair_confirmation.sql`
- `006_notifications_and_push_subscriptions.sql`
- `007_threaded_replies_and_edit_history.sql`
- `008_inline_media_tokens.sql`
- `009_soft_delete_posts.sql`
- `010_diagnostic_update_history.sql`
- `011_profiles_personal_help_and_publication.sql`
- `012_post_composer_drafts_and_device_catalog.sql`

The duplicate `002_` prefix is a review item. Do not infer execution order from the prefix alone.

## Efficient trace
Find route → controller method/argument names → imported service/model calls → tables/migrations → view/layout → CSS/JS and network requests → auth/CSRF/storage/logging. Consult Graphify output when a successful audit artifact is available.
