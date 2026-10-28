# Content system: behavior and acceptance checklist

This document describes the expected behavior after the content-system migrations are applied. It is a test plan, not a claim that every scenario has already passed in the local Kali/XAMPP environment.

## Content types

- **Help request:** requires a device/model, hardware category, description, and measurements/tests.
- **Technical post:** allows a repair note, technical information, or solved fault write-up.
- **General discussion:** allows a technical discussion without requiring device-specific measurements.
- The feed reads saved database records; demo data is not used as the primary feed source.
- **My Posts** lists the signed-in user's content types and supports type/status filters.
- **Workbench** lists the user's help requests with reply/update counts and lifecycle state.

## Request lifecycle

1. A request starts as `open`; replies and diagnostic updates are available.
2. The request owner may accept a reply, which changes the state to `awaiting_confirmation`.
3. Only the owner may confirm repair success. Confirmation requires the accepted reply, final cause, and successful repair action; only then does the status become `resolved`.
4. If the repair did not work, the owner can reject confirmation and return the request to `in_progress`.
5. Closing without repair creates an unresolved result; it does not masquerade as a successful repair.
6. Reopening a reviewed resolved/unresolved request clears stale accepted-reply and confirmation metadata.
7. Deleting an accepted reply clears the accepted reference and returns the request to diagnosis.
8. Result review and reputation events remain in the existing review flow. Unresolved results do not award repair-success points.

## Comments, diagnostics, and media

- Replies support one level of nested response threads.
- Authors may edit or soft-delete their own comments while the discussion is active. Previous comment text is stored in edit history.
- Diagnostic updates are a separate timestamped timeline; editing the original post does not create a diagnostic update.
- The request owner can edit or soft-delete diagnostic updates; prior text is stored in a separate history table.
- The shared editor inserts media using explicit `[[image:ID]]` tokens, not arbitrary HTML. Only known attachments for the current issue are rendered; text is escaped.
- Selected media can be inserted at the text cursor, removed, and reordered. Unreferenced inline media is cleaned up; ordinary attachments remain attachments.
- Images in issue content open in a responsive zoom/pan viewer.

## Notifications and PWA Push

- In-app notifications cover replies, replies to comments, diagnostic changes, accepted solutions, repair confirmation, status changes, and result review.
- Opening a notification marks it read and navigates to its internal target.
- Push delivery requires the Web Push Composer dependency and VAPID keys in the server's private `.env`; see [PWA Push setup](pwa-push-setup.md).
- The service worker caches public static files only; HTML pages and private notification data stay network-only.

## Manual acceptance test plan

Record the actual result on the local application after migrations and Composer dependencies are installed.

- [ ] Create one post of each content type; confirm the feed shows each saved record.
- [ ] Confirm old issue rows retain their IDs, owners, replies, attachments, results, and review state after migration.
- [ ] Reply to a help request, reply to a specific comment, and confirm the parent thread is rendered correctly.
- [ ] Edit and soft-delete a comment; verify the previous text is retained in history and the accepted-solution reference is reconciled if necessary.
- [ ] Add, edit, and soft-delete a diagnostic update; verify timestamps and history.
- [ ] Upload an allowed image and PDF; reject a disallowed type, a file over 8 MB, and more than 8 files.
- [ ] Insert two images between text, reorder them, remove one, submit, and verify the rendered order and zoom/pan viewer.
- [ ] Accept a reply; verify the request is **not** resolved yet. Confirm successful repair and verify it then becomes resolved.
- [ ] Reject repair confirmation; verify status returns to in-progress and a diagnostic note is recorded.
- [ ] Delete an accepted reply; verify accepted-solution metadata clears and the request returns to diagnosis.
- [ ] Review a repaired result and an unresolved result; verify points are awarded only for an approved repaired result and never twice.
- [ ] Trigger each notification event; verify unread count, mark-read behavior, correct target, and no self-notifications.
- [ ] Enable and disable Push on HTTPS in at least one Chromium-based browser and, if available, an installed iOS PWA.
- [ ] Verify mobile, tablet, and desktop layouts; confirm no private page or notification response is cached.
- [ ] Run PHP lint and the existing project checks; record any failures before considering the feature complete.

## Verification record

- PHP lint: **not verified from the local Kali runtime in this session**.
- Database migrations: **not executed in this session**.
- Browser/PWA/Push tests: **not executed in this session**.
