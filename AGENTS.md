# AI Contributor Instructions — Zayan Hardware

These rules apply to every AI or human contributor.

## Required entry point
1. Read `docs/ai/README.md` and `docs/ai/CURRENT_STATE.md`.
2. Verify the actual branch, HEAD and working tree. Treat this handoff as a snapshot, not runtime truth.
3. Locate the smallest feature slice in `docs/ai/FEATURE_MAP.md`.
4. Trace the real path: route and HTTP method → named route parameters → controller signature → service/model → database tables/migrations → views/layouts → CSS/JS → storage, authorization and notifications.
5. Inspect source at the active branch before editing; do not assume another branch is current.

## Product and architecture
- Product scope: mobile-phone hardware, board-level repair, technical diagnosis, measurements and schematics.
- Exclude CRM/customer management and mobile software-repair features unless the owner changes scope.
- Preserve the custom PHP MVC architecture: Core, Controllers, Models, Services and Views.
- Arabic-first RTL, responsive behavior, shared design tokens and existing interaction patterns are required.

## Safety and change discipline
- Never expose, request or commit secrets. Do not read or print the full `.env`; use `.env.example` and redacted checks.
- Never perform destructive Git, database, filesystem or permission operations without explicit approval and a verified backup/rollback plan.
- Never use `chmod 777`, broad recursive ownership changes or indiscriminate cleanup.
- Do not run migrations against live data without explicit authorization.
- Preserve local uncommitted and untracked files. GitHub state, local checkout, deployment and live DB are separate.
- Keep private uploads and logs outside the public document root.
- Do not change unrelated features or redesign established pages without an explicit request.

## Routing contract
- Every route placeholder must match a controller parameter name exactly; the Router uses named argument unpacking.
- Check the controller parameter type. Only parameters explicitly listed by `Router::INTEGER_ROUTE_PARAMETERS` are numeric and cast to integers.
- UUIDs, usernames and other text identifiers must remain strings.
- Mutations must use POST, CSRF verification, server-side validation and object-level authorization.

## Verification and handoff
- Run focused tests plus PHP and JavaScript syntax checks where possible.
- Use the Graphify workflow to inspect code relationships; do not claim generated output exists until a successful run is verified.
- Review the diff. Record real test results and any unverified items.
- Update CURRENT_STATE, CHANGELOG, GRAPH and relevant feature/architecture docs after meaningful changes.
- Never describe unmerged code as deployed.
