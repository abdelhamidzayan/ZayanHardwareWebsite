# Graphify — Zayan Hardware

Graphify is a repeatable, dependency-free static code-graph generator maintained in this repository. It reads source files only. It does not boot the application, connect to MySQL, execute SQL, or modify application data.

## Generate the graph locally

Requires Python 3.10+ and only the Python standard library.

~~~bash
python3 tools/graphify/graphify.py
~~~

This creates two files under .graphify/:

- graph.json — the complete machine-readable node and edge graph.
- architecture.mmd — a Mermaid view of the main route, class, and database-table relationships.

Check whether an existing graph matches the current source:

~~~bash
python3 tools/graphify/graphify.py --check
~~~

Run the analyzer tests:

~~~bash
python3 -m unittest discover -s tests/python -v
~~~

Generated local output is ignored by Git. GitHub Actions runs the tests, generates the graph for the pull request, and uploads both output files as a workflow artifact.

## What it maps

| Source | Nodes | Relationships |
|---|---|---|
| PHP | Files, classes, methods | Class definitions, imports, inheritance, static class usage, object construction, literal includes |
| Routes | HTTP route declarations | Route to controller and controller method |
| SQL migrations | Migration files, tables | Tables created or altered and foreign-key references |
| JavaScript | JS files | Relative JS imports and scripts loaded by PHP views |

Node IDs are stable for the same node type and source key. Output ordering is deterministic, so graph diffs remain reviewable. The scanner also reports duplicate three-digit migration prefixes. This is important because this repository has alternative files with the 002 prefix.

## Reading the result

Open .graphify/architecture.mmd in a Mermaid-compatible Markdown viewer. Use .graphify/graph.json for programmatic queries or later visualization tools. Each edge records its relationship type, for example routes_to_controller, uses_class, creates_table, or foreign_key_references.

## Accuracy boundaries

This is static source analysis, not a PHP or JavaScript compiler and not a runtime call trace. It does not guess dynamic class names, reflection, variable route construction, arbitrary SQL assembled at runtime, framework magic, or all method-to-method calls. Grouped PHP imports and complex language syntax may not be resolved. A missing edge means “not detected by this scanner,” not proof that no runtime dependency exists.

Use the graph to navigate and prioritize code review, then verify important paths against source and tests. It does not replace integration testing, security review, or schema inspection.

## Safety boundaries

- The scanner reads source trees under app, bin, config, public, tests, and database/migrations.
- It explicitly skips storage, vendor, node_modules, and Git internals.
- It does not read .env, database credentials, private uploads, or production data.
- It does not execute migrations or the account-retention worker.
- Technical posts, attachments, schematics, diagnostic history, and other application data are never modified by Graphify.
