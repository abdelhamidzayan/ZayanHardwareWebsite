# GitHub Actions and CI Usage Policy

This policy keeps automated verification available while preventing avoidable Actions usage and unexpected overage charges.

## 1. Why the workflows stay in the repository

GitHub Actions is part of the project's verification system. The workflows validate PHP and JavaScript, run focused regression tests, install the complete schema from an empty disposable database, exercise MySQL/MariaDB compatibility, and check Graphify output.

- Keep workflow YAML files version-controlled.
- Do not delete workflows or permanently disable them just because the account reached its included-minute limit.
- Do not describe an untested commit as passing CI.
- CI databases and fixtures must be disposable and synthetic. Never point a workflow at the owner's local, hosted, or production database.

## 2. When the included Actions minutes are exhausted

When GitHub reports that the included Actions minutes are exhausted:

1. Stop manually rerunning failed jobs, dispatching workflows, or repeatedly pushing commits only to retry CI.
2. Check the exact usage and reset date shown in the repository owner's GitHub billing/usage page.
3. Check the Actions spending budget before any usage that could be billed beyond the included allowance. Where supported, use a **$0 budget** for Actions to block overage spending. Confirm that the budget covers the relevant Actions usage; never assume it has been configured.
4. Keep the workflow files and their test matrix intact while waiting for the displayed reset or explicit authorization for paid usage.
5. Do not interpret a run with no executed steps as an application-test result. It may have been prevented from starting because the account exhausted its quota.

A quota-related workflow failure is a verification blocker, not proof that the code passed or failed.

## 3. Resuming CI after the reset

After the billing page shows that the included minutes have reset:

- Verify the account's current usage and spending controls first.
- Identify the final commit SHA to be tested.
- Batch related changes before starting the full matrix.
- Run the relevant workflows once against that final SHA, rather than repeatedly triggering the same matrix for every small documentation or code edit.
- If any job fails, inspect its logs and fix the underlying issue before rerunning the affected checks.

A green result applies only to the exact commit and checks executed by that run.

## 4. Workflow trigger and concurrency discipline

To reduce duplicate runs without removing the quality gate:

- Keep `pull_request` enabled for proposed code changes.
- For the PHP syntax and database integration workflows, use push triggers on `main` rather than every working branch when pull-request checks already cover those branches.
- Use a concurrency group so a newer run for the same pull request or branch can cancel an obsolete in-progress run. Keep the group expression aligned across the workflows that share the same trigger model.
- Keep manual `workflow_dispatch` only where a maintainer has a clear reason to start a dedicated analysis run.
- Review Graphify's branch filters separately: it currently retains extra feature-branch push triggers and does not yet use the same concurrency block as the PHP/database workflows.

Concurrency is a cost-control and freshness measure. It does not replace any required checks, and a cancelled run is not a passing run.

## 5. How verification must be reported

Every audit update must distinguish these states:

| State | Meaning |
|---|---|
| Implemented | The change exists in source control. |
| Static-checked | Syntax, source inspection, or deterministic static analysis completed. |
| CI-tested | The named workflow passed on the exact recorded commit SHA. |
| Runtime-verified | The behavior was exercised in the stated local or disposable runtime. |
| Deployed | The change was deliberately deployed to the named environment. |

Never promote one state into another. A historical green run does not verify later commits, browser behavior, PWA installation, or deployment.

## 6. Owner approval and safety boundaries

- Do not authorize paid Actions overage or change billing settings on the owner's behalf.
- Do not merge or deploy an audit branch just because its CI later passes.
- Do not reset, migrate, or write to a real database as part of CI.
- Keep technical posts, replies, diagnostic history, schematics, attachments, and edit history protected from physical deletion.

## Current handoff note

The owner reported that the account had used **2,000 / 2,000 included minutes**, and the GitHub notice showed a reset on **2026-11-01**. At the time this handoff was written, the owner had not confirmed whether a $0 Actions spending budget was active. Recheck the live billing page and the current branch/commit before triggering workflows; this note is historical, not a live billing-status guarantee.
