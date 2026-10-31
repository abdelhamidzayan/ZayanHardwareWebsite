# Zayan Hardware — Current Project State

> **Last documented:** 2026-10-31  
> This is a compact handoff, not a substitute for checking the live repository, local checkout, database, or deployment.

## 1. Current development direction

The owner's current priority is to **finish the planned product features first**. The broad, time-consuming, full-system audit and final Graphify/security/documentation sweep are deferred until the planned feature set is complete.

This does **not** defer essential safety:
- Every feature still receives focused validation and relevant regression tests.
- Authentication, authorization, CSRF, input validation, private-file access, and data-retention rules must be respected while implementing the feature.
- Destructive database/filesystem operations still require explicit approval, a verified backup, and a rollback plan.
- Documentation is updated as part of every meaningful feature delivery.

Do not restart a full-system audit or rebuild existing architecture unless the owner explicitly asks.

## 2. Git and release state — dated snapshot

- The documentation baseline for this handoff was prepared from `audit/system-rebuild-20261031`, PR [#16](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/pull/16).
- At the last check on 2026-10-31, PR #16 was **Draft, open, and unmerged**, based on `audit/content-retention-consistency`.
- This documentation work is on its own branch: `docs/ai-handoff-and-feature-log-20261031`.
- The default branch `main`, PR branch, documentation branch, owner's local checkout, local XAMPP instance, and any hosted deployment are separate states.

**Before every task, query the live branch/PR head and inspect the actual checkout.** The branch names and SHAs above are dated references only. Do not assume these documentation changes are already in `main`, in the owner's local checkout, or deployed.

## 3. Product boundaries and non-negotiable data rules

Zayan Hardware is an Arabic-first, RTL technical community for mobile-phone hardware repair, board-level diagnosis, measurements, schematics, and repair outcomes.

- Keep the existing PHP MVC architecture: Core → Controllers → Services / Models → Views.
- Do not add CRM/customer-management or mobile software-repair scope without owner approval.
- Never physically delete technical posts, replies, diagnostic updates, repair results, schematics, attachments, edit history, reputation, or approved help knowledge as part of normal account/content operations.
- Preserve stable technical attribution and user IDs. Account retention must follow the documented anonymization/preservation policy; do not replace it with physical user deletion.
- Never bulk-delete media or run retention workers against real data without explicit approval, verified backup, and rollback plan.

Read [AGENTS.md](../AGENTS.md) and [AI_CONTEXT.md](../AI_CONTEXT.md) for the full rules and detailed historical handoff.

## 4. Existing implementation — read before extending

The current documented codebase already includes the following areas. These are starting points for source inspection, not permission to assume every path is bug-free or every test is green.

| Area | Existing implementation / important constraint | Reference |
|---|---|---|
| Architecture | Custom PHP MVC, named route parameters, services, models, views, vanilla JS/CSS, PWA | [Architecture map](architecture-code-map.md) |
| Technical content | Soft-delete behavior and DB guards protect technical history; reply tombstones preserve reply-tree relationships | [Content implementation](content-system-implementation.md), [Content migrations](content-system-migrations.md) |
| Content writes | `ContentWriteService` coordinates issue/reply/diagnostic writes and staged private-file moves; failure recovery needs focused tests | [AI handoff](../AI_CONTEXT.md) |
| Draft media | Revision-aware draft media handling and durable recovery-task records exist; pending tasks do not imply an automatic recovery worker | [AI handoff](../AI_CONTEXT.md) |
| Personal help | Message and attachment writes use separate transactions; partial attachment failure is possible and reported | [Profiles and personal help](profiles-personal-help.md) |
| Administration | Sensitive reviewed writes revalidate active admin authorization at the model/service boundary and record audit events transactionally | [AI handoff](../AI_CONTEXT.md) |
| Logging | Structured daily logs are documented under `storage/logs/`; logs and private uploads must remain outside `public/` | [README logging section](../README.md#logging-and-diagnostics) |
| Security | Rate limits, private media, account lifecycle, and upload safeguards have dedicated documentation | [Security hardening](security-hardening.md), [Account retention](account-retention.md) |
| Graphify | A deterministic source graph exists as a static-analysis aid; it is not a runtime trace | [Graphify guide](graphify.md) |

## 5. Verification caveats carried from the last handoff

- The last recorded successful database CI baseline covered migrations 000–017. Migration 018 and the newest content-write/recovery test changes were documented as awaiting successful CI verification at the time of the handoff.
- GitHub Actions usage was reported exhausted on 2026-10-31, with a displayed reset date of 2026-11-01. Recheck the account's live usage/budget before rerunning or dispatching workflows; do not assume a spending cap is configured.
- A CI run with no executed steps is neither a passing nor failing application test.
- The local backup, local database migration state, local uncommitted changes, and local runtime details are recorded as dated information in [AI_CONTEXT.md](../AI_CONTEXT.md). Recheck them on the actual machine before acting.
- Existing tests, historical green CI, or documentation do not prove that the current branch is tested, the local site works, Safari/PWA works, or the project is deployed.

## 6. Feature delivery workflow

For each requested feature:

1. Read [AGENTS.md](../AGENTS.md), [AI_CONTEXT.md](../AI_CONTEXT.md), this file, [UPDATES.md](../UPDATES.md), and the relevant feature documentation.
2. Check `git status`, active branch, commit, and current source. Preserve all modified/untracked files.
3. Trace only the feature's real dependencies end to end: route → controller → authorization/CSRF/rate limit → service → model/SQL → migration → view/UI → JS/API → storage/logs/notifications → tests.
4. Implement the smallest complete change that fits the existing architecture. Avoid unrelated refactors and duplicate systems.
5. Run focused tests and checks relevant to the feature, including regression/failure paths. Use broader checks when the change affects shared architecture, security boundaries, or database foundations.
6. Update [UPDATES.md](../UPDATES.md), this file when the overall state changes, and the relevant feature/architecture/migration documents. Update [README.md](../README.md) when setup, project entry points, or top-level navigation changes.
7. Report changed files, tests actually run, results, manual steps, and anything still unverified.

Do not run a full Graphify review or full-system audit for every small feature. Use the existing graph and guide when a change crosses architectural boundaries or when the owner requests it.

## 7. Next task

The next feature is selected by the owner in the conversation. Do not invent a feature or start unrelated cleanup while waiting for its scope.
