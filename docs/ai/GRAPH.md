# Curated Code Relationship Graph

This is a human-reviewed, source-derived graph, not a Graphify-generated artifact. The automated graph is produced by the Graphify workflow and must be reviewed before treating inferred edges as facts.

## Application request flow
```mermaid
flowchart TD
  Browser[Browser or PWA] --> Entry[public/index.php]
  Entry --> Boot[App::boot]
  Boot --> Config[config/config.php and database.php]
  Entry --> RouteMap[config/routes.php]
  RouteMap --> Router[Router::dispatch]
  Router --> Controllers[Controllers]
  Controllers --> Services[Domain services]
  Controllers --> Models[Models]
  Services --> Models
  Models --> PDO[(MySQL via Database)]
  Controllers --> View[View::render]
  View --> Templates[Views and layouts]
  Templates --> Assets[Public CSS and JavaScript]
  Boot --> Logs[storage/logs]
  Controllers --> PrivateFiles[storage/private]
```

## Verified feature relationships
```mermaid
flowchart LR
  Routes[config/routes.php] --> IssueC[IssueController]
  IssueC --> IssueM[Issue model]
  IssueC --> Editor[ContentEditorService]
  IssueC --> IssueViews[issues views]
  Routes --> DraftC[DraftController]
  DraftC --> DraftM[PostDraft]
  DraftC --> Publish[DraftPublishingService]
  Publish --> IssueM
  Publish --> PrivateFiles[storage/private]
  Routes --> ProfileC[ProfileController]
  Routes --> AvatarC[AvatarController]
  Routes --> HelpC[HelpRequestController]
  ProfileC --> UserM[User]
  AvatarC --> UserM
  AvatarC --> AvatarSvc[AvatarService]
  HelpC --> HelpM[HelpRequest]
  HelpC --> HelpMedia[HelpRequestMediaService]
  HelpC --> Notify[NotificationService]
  Routes --> NotifyC[NotificationController]
  Routes --> PushC[PushController]
  NotifyC --> NotifyM[Notification]
  PushC --> PushM[PushSubscription]
  Notify --> WebPush[WebPushService]
  WebPush --> PushM
  WebPush --> Worker[public/service-worker.js]
  Routes --> DeviceC[DeviceCatalogController]
  DeviceC --> DeviceM[DeviceCatalog]
  Routes --> SkillC[SkillTestController]
  SkillC --> SkillSvc[SkillTestService]
```

## Frontend relationships
```mermaid
flowchart TD
  Tokens[public/css/tokens.css] --> AppCSS[app.css]
  Tokens --> Components[components.css]
  Tokens --> Pages[pages.css]
  Responsive[responsive.css] --> Layouts[View layouts]
  Layouts --> Header[header and navigation partials]
  Layouts --> AppJS[public/js/app.js]
  Composer[composer views] --> ComposerJS[post-composer.js and drafts.js]
  HelpViews[help request views] --> HelpJS[help-requests.js]
  Notifications[notification views] --> NotificationJS[notifications.js]
  Manifest[manifest.webmanifest] --> SW[service-worker.js]
  SW --> StaticAssets[Approved static assets]
```

## Route-to-signature contract
- `GET /avatar/{userId}` → `AvatarController::show(int $userId)` → `Router::INTEGER_ROUTE_PARAMETERS` normalizes and casts `userId`.
- `GET /profile/{username}` → `ProfileController::show(string $username)`; it remains a string.
- `/drafts/{draftId}/media/{mediaUuid}` → DraftController methods accept string UUID parameters; they remain strings.
- Issue/help/admin IDs use `{id}` and integer controller parameters.

## Maintenance rules
Add source paths and line references to high-value new edges. Mark uncertain relations Unverified. Keep this curated diagram smaller than Graphify's full symbol-level output. Review generated graph JSON/HTML for false edges, missing modules, noisy dependencies and private-path leakage before relying on it.
