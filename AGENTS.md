# AI and Contributor Rules — Zayan Hardware

Before any work, read [AI_CONTEXT.md](AI_CONTEXT.md) and [the architecture map](docs/architecture-code-map.md).

## Product scope
- Build an Arabic-first RTL mobile-hardware technical community for board repair, diagnosis, measurements, schematics, and repair outcomes.
- Do not add CRM/customer-management or mobile software-repair scope without explicit owner approval.
- Preserve the existing PHP MVC boundaries: Core, Controllers, Services, Models, Views. Reuse the established UI tokens and responsive patterns.

## Safety and data preservation
- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or published help knowledge.
- Never run destructive migrations, account-retention workers, bulk media cleanup, or database/filesystem deletion against real data without explicit approval, a verified backup, and a rollback plan.
- Preserve all local modified and untracked files. Inspect `git status` before updating. Never use destructive reset/clean operations.
- Never expose, request, or commit secrets. Do not print `.env`; use redacted checks and `.env.example`.
- Keep private uploads and logs outside `public/`. Do not use `chmod 777` or broad recursive ownership changes.

## Architecture and security
- Trace every feature from route/method and named parameters through controller, authorization, CSRF, rate limiting, service, model/SQL, migrations, views, JS/CSS, storage, logs, and notifications.
- Route placeholders must match controller parameter names exactly. Respect integer-vs-string/UUID parameter contracts.
- Mutations require server-side validation, object-level authorization, and CSRF protection.
- Private file delivery requires participant/owner authorization, canonical path containment, MIME validation, and safe streaming headers.
- Database migrations are applied-state dependent. Never infer that a migration ran because its file exists; inspect the target schema/history first.

## Graphify and verification
- Use `python tools/graphify/graphify.py`, `--check`, and `python -m unittest discover -s tests/python -v` for the repository's deterministic Graphify scanner.
- Treat the graph as static analysis, not a runtime trace. Review unresolved API calls and warnings manually.
- Run focused regression tests plus PHP/JS syntax and relevant database integration tests.
- Review diffs and record what is implemented, tested, runtime-verified, and deployed separately.

## Git discipline
- Do not merge PRs, push to the default branch, rewrite history, discard stashes, or overwrite owner changes without explicit approval.
- Work on a dedicated branch and keep changes focused. Never describe a draft/unmerged PR as deployed.
