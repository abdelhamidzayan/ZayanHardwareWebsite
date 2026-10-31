# Member Profiles, Avatars and Personal Help

## Profile images
- Every member has a built-in avatar key by default.
- Members can choose one of the bundled technician avatars or upload a JPG, PNG, or WEBP image.
- The browser opens a square crop tool with drag and zoom. The server validates MIME, size and dimensions and re-encodes to JPEG when GD is available.
- Custom avatar files are stored under `storage/private/avatars/{user_id}/` and served only through the validated avatar controller route.
- Technical board/schematic attachments keep their original aspect ratio. Cropping is only for the member avatar so technical details are not lost.

## Personal help requests
- Members control `accept_help_requests` from account settings.
- A request is created from another member's public profile and starts as `pending`.
- Only the receiving member can accept or decline it.
- Accepting opens a private, request-scoped conversation. Participants can exchange text, images and PDFs.
- Only the two participants can view the request, messages and private attachments.
- Either participant can close an active request. Closed conversations remain available to both participants as read-only archives.
- The header conversation menu shows the latest requests and unread states. Opening a conversation updates that participant's read marker.

## Public help library and consent
- Only the receiving member/helper can propose a public reference, and only after the request is closed.
- The helper writes a separate public summary (problem, diagnosis/measurements and solution). The private message thread and private attachments are not published automatically.
- The helper chooses whether their own identity is shown when proposing publication.
- The requester must explicitly approve publication. Only after approving publication are they asked whether their own identity should be shown.
- Each participant's identity choice is independent. A hidden identity means no name, profile ID or avatar is copied to the public record.
- If the requester rejects publication, no public post is created. The closed private conversation remains visible only to its two participants.
- Help requests, messages and public sharing do not award Points.

## Database
Run `database/migrations/011_profiles_personal_help_and_publication.sql` once after migration 010.
It adds avatar and help-availability fields to `users`, private help request/message/attachment tables, read markers, consent records and public help references.

## Routes
- `/profile/{username}` — public member profile and help request entry point.
- `/settings` — avatar selection/upload and help-request availability.
- `/help-requests` and `/help-requests/{id}` — private inbox and conversation archive.
- `/help-library` and `/help-library/{id}` — public references approved by both participants.

## Deployment notes
- Ensure `storage/private` is writable by the PHP/Apache process and is not exposed as a public web directory.
- PHP fileinfo is required for upload MIME validation. GD is recommended for server-side avatar re-encoding.
- Configure PHP upload limits to allow the documented 5 MB avatar and 8 MB message attachments.
