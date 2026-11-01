# Post composer, device catalog and resilient drafts

This specification applies to the Zayan Hardware post-creation flow. It is intentionally additive: existing issue IDs, comments, replies, diagnostic history and help-request workflows remain intact.

## Agreed publishing experience

1. The user selects the manufacturer from a visual list (brand mark plus name), then searches the model.
2. Model lookup is scoped to the selected manufacturer and uses prepared SQL search conditions over commercial name, model code and aliases. A technician may search by code alone (for example, X605) without knowing the marketing name.
3. If no result exists, the user can suggest a model. Suggestions are pending until an administrator approves them; they do not become selectable models before approval.
4. The two existing public types “معلومة فنية” and “نقاش عام” are presented as one type, “معلومة فنية”. Existing `discussion` rows remain readable and are displayed with the unified label. The separate “طلب مساعدة” workflow remains separate.
5. The title is the visual hero of the post. The body is either written text or one audio recording; both are never active at the same time. Existing legacy posts remain readable.
6. Steps and tested checks are optional, compact, sequential, editable single-line inputs. Pressing Enter creates and focuses the next input; deleting an entry renumbers the remaining list. The UI displays only a numbered circle and the text; numbering always follows the current order.
7. A single audio recording supports pause/resume, stop, preview, waveform display and trim points. The audio UI uses the same visual treatment as the home-feed voice player.
8. Attachments are added from one paperclip control at the bottom of the form. Images and videos are previewed in a small ordered gallery. Image editing supports crop, free text, pen, arrows, circles, rectangles and other basic shapes, color/size controls, selecting/moving/resizing/deleting individual objects, undo/redo and clearing edits.
9. Image edit data remains editable until the user confirms the edit. The original image and normalized editor state are preserved separately from any rendered preview.
10. In the published post, text and title retain visual priority. Images remain small thumbnails; clicking opens a contained viewer with previous/next navigation, zoom and an explicit full-screen mode.

## Post-details editor behavior

- The post-details editor keeps CodeMirror 6, its live preview, undo/redo history, keyboard navigation and textarea synchronization for form submission and draft persistence.
- The formatting toolbar is intentionally removed. Markdown is typed directly: `**text**` and `*text*` render bold in the live preview while the raw Markdown remains in the stored description.
- Bulleted (`- `) and numbered (`1. `) lists continue on Enter. Pressing Enter on an empty list item exits the list.
- Tested steps are single-line inputs. Enter appends and focuses the next input; numbering is derived from current order and updates after deletion.

## Draft and storage architecture

- SQL stores draft ownership, revision, timestamps and structured metadata only. Binary images, video and audio are never stored as SQL BLOBs or Base64 strings.
- Private server storage holds draft media and published originals/derivatives outside the public web root.
- IndexedDB holds the current draft payload and pending File/Blob objects on the current device. It is a local recovery copy, not the canonical account record.
- The authenticated server draft is the cross-device source of truth. Debounced saves send structured changes and media uploads separately.
- Every draft has a monotonically increasing revision. A stale client receives a conflict response with the latest server revision/payload; it must not silently overwrite newer edits.
- Media upload uses allow-listed MIME detection, random storage names, size limits, ownership checks and private download routes. Draft media is promoted to issue attachments on publish rather than copied into duplicate permanent files.
- Refresh recovery loads local IndexedDB first for immediate restoration, then reconciles with the server revision. If both contain different newer edits, the user is shown a conflict/recovery choice rather than losing either copy.
- Service worker caching is limited to static assets. Draft payloads, account pages, media upload responses and private media are never cached by the service worker.
- “مسوداتي” lists the member's drafts, last-edit time and sync state, and lets the owner resume or delete a draft. Drafts are not silently deleted while active.

## Database migration notes

Migration 012 creates the manufacturer/model catalog, moderated model suggestions, structured steps/audio fields, media editor metadata and revisioned drafts. It is only a migration file for review; it must not be run until the schema diff is reviewed and a database backup is available.

## Validation required before merge

- Apply migrations 001–012 to a disposable database and verify foreign keys and JSON columns on the exact MySQL/MariaDB version used by hosting.
- Confirm old `help_request`, `technical_post` and `discussion` rows still render; confirm old attachments and inline media still work.
- Exercise model search by both commercial name and model code; submit and moderate a model suggestion using two roles.
- Test text/audio exclusivity, sequential steps, image/video type and size validation, image annotation round-trip and audio pause/resume/trim.
- Test draft refresh recovery, offline edits, retry, cross-device resume and revision conflict with two simultaneous clients.
- Verify private storage cannot be served directly and that only the owning member can read/update/delete a draft or its media.
- Test desktop, Android and iOS/PWA layouts and recorder capability differences. Do not claim microphone recording works in a browser context until permission, secure-context and MIME support are checked.