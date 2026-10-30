# Decision Register

Durable accepted decisions. Add superseding entries when the owner changes a decision.

## D-001 — Mobile hardware product scope
- Status: Accepted.
- Focus on mobile-phone hardware, board-level repair, diagnosis, measurements and schematics.
- Exclude CRM/customer management and mobile software repair unless the owner changes scope.
- Source: owner direction, 2026-10-25.

## D-002 — Arabic-first RTL
- Status: Accepted.
- Arabic is the primary interface language; RTL is the default. Use logical CSS properties and isolate technical identifiers/measurements in LTR where needed.
- Source: owner direction and application configuration.

## D-003 — Preserve custom MVC
- Status: Accepted.
- Continue the existing Core, Controllers, Models, Services and Views architecture. Do not add a parallel framework without explicit approval.
- Source: repository architecture and owner direction.

## D-004 — Public document root
- Status: Accepted.
- Configure the web document root to `public/`. Keep app code, config, logs and private data outside it.
- Source: repository structure and README.

## D-005 — Private files stay private
- Status: Accepted.
- Private uploads and sensitive runtime files belong under `storage/private/`; logs remain under `storage/logs/`. Serve private files only after authorization.
- Source: feature code and documentation.

## D-006 — PWA cache is static-only
- Status: Accepted.
- Never cache authenticated pages, private media, draft payloads, API responses or user-specific data.
- Source: PWA and composer requirements.

## D-007 — Composer changes remain compatible
- Status: Accepted.
- Preserve issue identity, replies, diagnostic history, help requests and legacy content readability.
- Source: composer/draft feature documentation.

## D-008 — Public help publication requires consent
- Status: Accepted.
- Private conversations and attachments are not automatically published. Publication requires the proposal/response flow and the requester's consent, with identity choice separate.
- Source: personal-help feature implementation and docs.

## D-009 — Shared design tokens
- Status: Accepted.
- Prefer established tokens/components and responsive patterns over one-off competing style systems.
- Source: `public/css/tokens.css` and owner feedback.

## D-010 — Git is the canonical AI project memory
- Status: Accepted process.
- Keep onboarding, decisions, architecture and handoff in `AGENTS.md` and `docs/ai/`; update after meaningful work.
- Source: owner request, 2026-10-30.

## D-011 — Graphify is analysis, not memory
- Status: Accepted process.
- Use Graphify to discover code relationships; keep reviewed decisions and project state in Markdown. Do not claim output exists until a successful run is verified.
- Source: owner request, 2026-10-30.
