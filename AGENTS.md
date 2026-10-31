# AI and Contributor Rules — Zayan Hardware

Before any work, read [AI_CONTEXT.md](AI_CONTEXT.md), [Current Project State](docs/CURRENT_STATE.md), [the Update Log](UPDATES.md), [the architecture map](docs/architecture-code-map.md), and the feature-specific documentation relevant to the task. Preserve and follow all existing rules below.

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
- Administrative mutations must revalidate the actor's active administrator role at the model/service boundary under a row lock; write the resulting state change and its security-audit event in the same database transaction. A role check cached earlier in the request is not sufficient.

## Graphify and verification
- Use `python tools/graphify/graphify.py`, `--check`, and `python -m unittest discover -s tests/python -v` when reviewing architectural dependencies or performing the requested Graphify review.
- Treat the graph as static analysis, not a runtime trace. Review unresolved API calls and warnings manually.
- For normal feature work, prioritize focused regression tests and PHP/JS syntax checks relevant to the changed code. Expand verification when shared architecture, security boundaries, or database foundations are affected.
- Do not run a full-system Graphify review or audit for every small feature. The owner currently wants feature development first and has deferred the broad audit until the planned feature set is complete, except for focused checks required by each feature.
- Review diffs and record what is implemented, tested, runtime-verified, and deployed separately.

## Mandatory feature documentation and AI handoff

Documentation is part of feature delivery, not optional cleanup.

- At the start of a task, follow the reading order in this file and verify the active branch/source. Documentation never replaces inspection of the actual code.
- For every meaningful feature, behavior change, bug fix, database change, or security change, add a concise newest-first entry to [`UPDATES.md`](UPDATES.md).
- Update [`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md) when the development direction, important implementation status, verification state, known limitation, or next milestone changes.
- Update the relevant feature, architecture, API, security, or migration document whenever its described behavior changes.
- Update [`README.md`](README.md) when setup instructions, project scope, top-level navigation, or important entry points change. Do not duplicate detailed feature documentation in README.
- Keep [`AI_CONTEXT.md`](AI_CONTEXT.md) as the durable handoff for cross-session facts, tricky implementation constraints, and dated environment/branch snapshots. Refresh dated details when they are re-verified; do not silently present old snapshots as current.
- Record tests exactly as run. Clearly distinguish implemented, locally tested, CI-tested, runtime-verified, and deployed. Never invent a passing test or imply a draft branch is deployed.
- Do not put secrets, credentials, private user data, or raw private logs in documentation.
- Use [the reusable AI start prompt](docs/AI_START_PROMPT.md) in a new conversation. It is only a short pointer to the repository's actual instructions and docs.

## GitHub Actions and usage budget
Read the detailed operating procedure in [docs/github-actions-policy.md](docs/github-actions-policy.md); the rules below are mandatory summary requirements.

- Keep the repository's GitHub Actions workflow files enabled and version-controlled. They are part of the project's verification system; do not delete them or permanently disable them to work around a quota limit.
- Required CI coverage includes PHP/JavaScript syntax and focused tests, the fresh-install migration sequence across the supported MySQL/MariaDB versions, database integration tests, and Graphify generation/determinism checks.
- Use pull-request events for branch verification and reserve automatic push-triggered runs for `main`, avoiding duplicate CI for the same PR update. Give each workflow a per-PR/ref concurrency group with `cancel-in-progress: true` so an older queued/running check is superseded by the newest commit.
- When GitHub reports that included Actions minutes are exhausted, do not repeatedly rerun failed jobs or dispatch new workflows. A run that has no executed steps is not evidence that the application tests passed or failed.
- Before any usage beyond the included allowance, verify the account's Actions billing budget. Use a **$0 spending budget** where supported to block accidental overage charges, and confirm that the budget applies to the relevant Actions usage. Do not assume this setting has been applied unless the owner confirms it.
- Preserve the workflow YAML and test matrix while the quota is exhausted. Resume the full relevant CI matrix only after the account's displayed reset date or after the owner explicitly confirms that paid Actions usage is authorized. Batch related changes so the same matrix is not needlessly repeated.
- Never mark an unverified commit as CI-passing. Record the exact commit SHA and separate implemented, CI-tested, locally/runtime-verified, and deployed states. CI must use disposable test databases and synthetic fixtures only.

## Git discipline
- Do not merge PRs, push to the default branch, rewrite history, discard stashes, or overwrite owner changes without explicit approval.
- Work on a dedicated branch and keep changes focused. Never describe a draft/unmerged PR as deployed.
