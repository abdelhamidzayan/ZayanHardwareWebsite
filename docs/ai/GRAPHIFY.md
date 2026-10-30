# Graphify — Code Relationship Audit

## Role
Graphify is the source-analysis tool for discovering relationships across files and symbols. It complements the curated architecture, feature map and decision register; it does not replace them.

Upstream project: https://github.com/Graphify-Labs/graphify  
The audit workflow pins the Python distribution `graphifyy[sql,leiden]==0.9.97` and uses the `graphify` CLI.

## Automated repository workflow
- Workflow: `.github/workflows/graphify-audit.yml`.
- Script: `.github/scripts/graphify_audit.py`.
- Branch scope: `audit/full-system-20261030`.
- The workflow checks PHP syntax, JavaScript syntax, a focused Router contract test, route/controller parameter types, controller-to-view references and migration-prefix notices.
- Graphify extracts a graph from approved PHP, JavaScript and SQL source paths, then produces graph JSON, report and health/consistency metadata.
- A route/view consistency failure or failed source extraction returns a failing exit code.
- Artifact name: `zayan-hardware-graphify-audit`; retention configured for 14 days.

## Source scope and privacy
- Approved roots: `app/`, `config/`, `public/` and `database/migrations/`.
- File types: PHP, JS and SQL.
- Exclusions include `.git`, vendor dependencies, node_modules, storage, uploads, cache, logs and Graphify output.
- The script does not scan `.env`, database dumps, backups or member uploads.
- Review upstream package behavior and any AI/semantic backend before changing versions. Do not add private user data or secrets to graph inputs or artifacts.
- Generated reports may expose internal source paths and architecture; keep repository/artifacts private and review before sharing.

## Generated output
The workflow uploads `graphify-out/`, including the structured graph/report/health data and HTML visualization when generation succeeds. Workflow artifacts expire after the configured 14 days. Review the source commit and extraction failures in metadata; do not treat an old artifact as the current graph.

## Local Kali workflow
Graphify has not yet been independently installed or run in the owner's Kali checkout as of this snapshot. To run locally:
1. Verify the currently installed Python/uv versions and check the upstream installation instructions.
2. Install the pinned or otherwise reviewed Graphify version in an isolated tool environment.
3. Run the repository's audit script from the project root.
4. Review the report, graph health, failed sources and private-path leakage.
5. Reconcile verified relationships into `docs/ai/GRAPH.md`; do not commit unreviewed generated output.

## Assistant integration limitation
A normal ChatGPT web conversation cannot execute Graphify against a local Kali filesystem. A CI workflow or supported local coding environment must run it and make reviewed output available. Merely adding Graphify files to Git does not mean Graphify has run.

## Evidence status
The script and workflow are committed on `audit/full-system-20261030`. No successful Graphify run or generated artifact is claimed yet.
