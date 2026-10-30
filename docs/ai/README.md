# Zayan Hardware — AI Project Memory

Start every new AI conversation here. This is the curated handoff and source index, not proof of the current runtime.

## Repository identity
- Repository: `abdelhamidzayan/ZayanHardwareWebsite` (private).
- Product: Zayan Hardware — عصارة الورش.
- Stack observed: PHP 8.2+ MVC, MySQL, vanilla JavaScript/CSS; web document root is `public/`.
- Main development branch at the audit baseline: `feature/profiles-help-media`.
- Full-system audit branch: `audit/full-system-20261030`.
- Audit source baseline: `1752f1b4c2acb48c5fbad182a0a3e82cace23c48`.

## Reading order
1. Root `AGENTS.md` — mandatory contributor rules.
2. `CURRENT_STATE.md` — latest verified handoff, commit and blockers.
3. `FEATURE_MAP.md` — locate routes, controllers, models, views, assets and migrations.
4. `ARCHITECTURE.md` and `GRAPH.md` — follow the system boundaries and relationships.
5. `DECISIONS.md` — durable product/architecture decisions.
6. `AUDIT_FINDINGS.md` and `CHANGELOG.md` — known defects, fixes and audit history.
7. Relevant feature documentation and the actual source files.

## Files
- `CURRENT_STATE.md`: branch/commit snapshot, verified state and next action.
- `ARCHITECTURE.md`: request lifecycle, MVC boundaries, shared security and storage rules.
- `FEATURE_MAP.md`: feature-to-source index.
- `GRAPH.md`: curated, source-derived relationships. It is not a Graphify-generated artifact.
- `AUDIT_FINDINGS.md`: confirmed issues, fixes, remaining concerns and verification limits.
- `DECISIONS.md`: accepted decisions and rationale.
- `CHANGELOG.md`: append-only engineering history.
- `GRAPHIFY.md`: Graphify setup, automated CI workflow and artifact handling.

## Evidence labels
- **Verified in source**: inspected at a stated commit.
- **Tested**: a test command was actually executed and its result observed.
- **User-reported**: supplied by the owner but not independently verified.
- **Unverified**: requires local, CI, browser, database or deployment confirmation.

Do not merge together repository code, local checkout, deployed site and live database as if they were the same state.

## New conversation handoff
Read `AGENTS.md`, this file and `CURRENT_STATE.md`. Verify branch/HEAD and the requested feature before editing. Continue from the latest audit state without re-asking already documented details. Preserve local-only changes. After changes, test, review the diff and update the relevant memory files.
