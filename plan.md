# Zayan Hardware — Post Composer Modernization Plan

> Canonical roadmap for the staged post-composer work. Read this file before every continuation.
> Base branch at plan creation: `main` @ `e640cce3e57a03ae275d18e359728cb164529b2a`.
> Working branch: `feature/post-composer-live-preview`.
> Last reviewed: 2026-11-01.

## Operating rules

- Read `AGENTS.md`, `AI_CONTEXT.md`, this plan, current state, update log, architecture map, composer documentation, and the actual current source before each stage.
- Work only on a dedicated feature branch. Never push to or merge into `main` without explicit owner approval.
- Do not start a later stage until the owner approves the preceding stage.
- Preserve all technical posts, attachments, historical revisions, drafts, local IndexedDB records, pending deletions, and existing private media.
- No database schema changes or migrations without stopping and obtaining explicit approval.
- Do not modify `content-editor.js`, comments, or replies.
- Record exact tests and distinguish source review, syntax checks, CI, runtime verification, and deployment.
- Never mark a stage complete based on implementation alone.

## Stage 1 — Modern technical details editor

**Status: BLOCKED — implementation not started; build-environment blocker must be resolved first.**

### Goal
Replace the basic textarea experience with a modern Arabic-first RTL CodeMirror 6 Live Preview editor inspired by Obsidian, aligned with the current Zayan Hardware cream/ivory and workshop-green brand identity.

### Confirmed current architecture
- `app/views/issues/create.php` owns the composer markup.
- `public/js/post-composer.js` owns composer state, local persistence, IndexedDB, synchronization, revisions, media state, and draft restoration.
- The details field is currently `textarea[data-details]`; payload collection reads its `.value`.
- The current format toolbar supports bold, heading, numbered list, bullet list, and divider.
- `app/services/ContentEditorService.php` is the server-side safe renderer.
- Legacy inline media markers and existing attachment retention must remain intact.
- `public/css/post-composer.css` is the feature stylesheet.
- `app/views/layouts/head.php` loads global CSS; `app/views/issues/create.php` loads composer scripts.
- The repository currently has PHP Composer tooling but no JavaScript package manifest or lockfile.
- The current main brand tokens are workshop green `#105023`, cream `#FFFCF3`, and ivory `#F8F3E0`.

### Design and behavior
- CodeMirror 6, locally bundled; no CDN.
- Compact accessible toolbar with touch targets at least 44 CSS pixels.
- Live Preview: inactive lines render supported formatting; active line reveals raw Markdown.
- Supported subset: bold, H1-H3, bullets, numbered lists, blockquotes, inline code, fenced code blocks, and horizontal dividers.
- Per-line bidirectional handling for Arabic and mixed English technical identifiers.
- Code and measurements use LTR/monospace presentation where appropriate.
- Editor height approximately 12 lines with internal scrolling and no resize handle.
- Mobile keyboard and viewport behavior must not obscure essential controls.
- Images remain separate composer attachments; do not add image insertion to the details editor.
- Keep the existing textarea synchronized as the compatibility and fallback field.
- CodeMirror is a view layer only. Existing `post-composer.js` remains the sole owner of draft state and saving.
- If the editor bundle fails to load, the textarea remains usable.

### Renderer compatibility and security
- Update only `ContentEditorService` formatting behavior if required; do not touch `content-editor.js`.
- Escape input before introducing whitelisted markup.
- Add safe rendering for quote, inline code, and fenced code blocks.
- Preserve unsupported syntax as text.
- Preserve legacy `[[image:ID]]` handling.
- Keep existing attachments and historical references; never prune them automatically.

### Tests and acceptance
- Shared PHP/JS formatting fixtures.
- PHP renderer tests for all supported syntax, malformed syntax, mixed Arabic/English, legacy media markers, and XSS payloads.
- JavaScript tests for formatting and textarea synchronization.
- PHP and JS syntax checks.
- Draft save/restore and revision-conflict regression coverage.
- Actual browser tests on desktop and mobile, including keyboard behavior, must be performed before calling the stage runtime-verified.
- No schema changes.
- Update composer docs, current state, and update log.

### Build blocker discovered during initial inspection
The repository has no JS package manifest/lockfile, the current execution environment has no network access, and a local clone attempt failed because GitHub DNS could not be resolved. Therefore a reproducible local CodeMirror 6 bundle cannot currently be generated or verified in this execution environment. Do not substitute a CDN, an unbundled remote import, or a fake/minimal editor while claiming CodeMirror 6 is complete. Resolve the build pipeline and produce the actual local bundle before declaring Stage 1 ready for browser testing.

### Stage 1 completion status
- Source inspection: completed for composer view, composer state owner, feature CSS, brand tokens, layout asset loading, and PHP renderer.
- Code changes: not yet implemented.
- Build pipeline: blocked pending a reproducible dependency install/build environment.
- Tests: not run.
- Browser/runtime: not run.
- PR: not opened.
- Merge/deployment: not performed.

## Stage 2 — Audio recording, recovery, and real trimming

**Status: NOT STARTED — planned only.**

- Inspect existing MediaRecorder, IndexedDB chunk/session recovery, preview, trim controls, and server publishing path.
- Implement actual physical trimming; playback-range-only trimming is insufficient.
- Keep original recording private and separate from the final published clip.
- Preserve current draft synchronization and recovery.
- Validate final audio structure, MIME, duration, and size server-side.
- Do not expose original recordings through public routes.
- No schema changes without explicit approval.

## Stage 3 — Audio playback presentation

**Status: NOT STARTED — planned only.**

- Add a polished accessible RTL player to the post detail page.
- Show compact microphone/duration metadata in feed cards.
- Avoid a full player in every feed card.
- Preserve protected audio routes and old recordings.

## Stage 4 — Image editor Core/UI separation and state v2

**Status: NOT STARTED — planned only.**

- Inspect the existing image editor and v1 state before designing migration behavior.
- Separate DOM-free image processing core from UI.
- Define and test image/composition/view coordinate transforms.
- Preserve v1 and unknown state properties.
- Keep selection overlays out of exported images.
- Implement tested touch/pointer interactions, tools, undo/redo, and export behavior.
- No schema changes unless separately approved.

## Stage 5 — PWA caching and final integration

**Status: NOT STARTED — planned only.**

- Inspect the actual service worker and registration path.
- Cache local editor assets safely with versioned invalidation.
- Preserve offline behavior and PWA installation.
- Run focused end-to-end checks across drafts, media, publishing, and protected routes.
- Complete final regression and documentation review.

## Pull request sequence

1. `feature/post-composer-live-preview`
2. `feature/post-audio-real-trimming`
3. `feature/post-audio-player-display`
4. `feature/image-editor-core-v2`
5. `feature/post-composer-pwa-integration`

Each stage must branch from the latest `main` after the preceding stage is approved and merged. The owner must explicitly approve merging.
