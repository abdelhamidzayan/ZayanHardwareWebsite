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
| PHP Models | Literal SQL fragments in model source | Model file to tables read by SELECT/FROM/JOIN and tables written by INSERT/REPLACE/UPDATE/DELETE/TRUNCATE |
| JavaScript and PHP views | JS files, API-call sites, PHP form/data attributes | Relative JS imports, scripts loaded by PHP views, form actions and data-* endpoint values, fetch/XHR calls resolved through those view-provided URLs to matching HTTP routes |

Node IDs are stable for the same node type and source key. Output ordering is deterministic, so graph diffs remain reviewable. The scanner also reports duplicate three-digit migration prefixes. This is important because this repository has alternative files with the 002 prefix.

## Reading the result

Open .graphify/architecture.mmd in a Mermaid-compatible Markdown viewer. Use .graphify/graph.json for programmatic queries or later visualization tools. Each edge records its relationship type, for example routes_to_controller, uses_class, creates_table, foreign_key_references, queries_table, writes_table, or calls_route. Graph statistics also report SQL fragments inspected and JavaScript API calls found, linked, or left unresolved.

## Accuracy boundaries

This is static source analysis, not a PHP or JavaScript compiler and not a runtime call trace. It does not guess dynamic class names, reflection, variable route construction, arbitrary SQL assembled at runtime, framework magic, or all method-to-method calls. SQL analysis only recognizes table identifiers present in literal SQL strings inside model files; queries assembled from fragments, passed through helper methods, stored in external files, or generated at runtime may be missed. JavaScript route linking only succeeds when the request URL and HTTP method are statically resolvable and match a declared PHP route. Calls built from DOM form actions and data attributes are resolved when the same PHP view statically supplies the URL and loads the associated script. Simple concatenations that combine a known data-* base URL with literal path fragments are also resolved. Other calls built from runtime-only DOM values, unknown base-path variables, or dynamically generated strings are retained as api_call nodes with dynamic/unmatched resolution rather than being guessed. The scanner is intentionally conservative: a missing edge means “not detected by this scanner,” not proof that no runtime dependency exists.

Use the graph to navigate and prioritize code review, then verify important paths against source and tests. It does not replace integration testing, security review, or schema inspection.

## Safety boundaries

- The scanner reads source trees under app, bin, config, public, tests, and database/migrations.
- It explicitly skips storage, vendor, node_modules, and Git internals.
- It does not read .env, database credentials, private uploads, or production data.
- It does not execute migrations or the account-retention worker.
- Technical posts, attachments, schematics, diagnostic history, and other application data are never modified by Graphify.
