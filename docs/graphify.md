# Graphify — Zayan Hardware

Graphify is a repeatable, dependency-free static code-graph generator maintained in this repository. It reads source files only. It does not boot the application, connect to MySQL, execute SQL, or modify application data.

## Latest CI snapshot — 2026-10-31

The Graphify artifact generated for Phase 1 head `c35c71f39d0702178d8dc9aad041d247b29c29b1` completed successfully in [workflow run 41238036104](https://github.com/abdelhamidzayan/ZayanHardwareWebsite/actions/runs/41238036104).

| Metric | Snapshot |
|---|---:|
| Source files scanned | 151 |
| PHP routes found | 72 |
| SQL query fragments analyzed | 168 |
| JavaScript API call sites | 15 |
| API calls statically linked | 10 |
| API calls dynamic/unresolved | 5 |
| Graph nodes | 698 |
| Graph edges | 1,637 |

Node inventory: 53 classes, 347 methods, 20 migrations, 72 routes, 40 database tables, 15 API-call sites, and 151 files.

The five dynamic/unresolved JavaScript call sites are not proof of broken endpoints. They use runtime values or request objects that the static matcher deliberately does not guess. Review them alongside [the manually maintained JavaScript API route map](javascript-api-route-map.md), then verify the relevant browser flows in Phase 3.

The analyzer also warns that two migration filenames share prefix `002_`. This is a naming warning, not an ordering ambiguity: `database/FRESH_INSTALL.md` defines the intended sequential order, and the disposable fresh-install integration suite passed on MySQL 8.0, MariaDB 10.4.32, and MariaDB 10.11. Never infer migration state from filename prefixes.

These counts describe one commit snapshot. Regenerate the graph after significant route, service, model, migration, or JavaScript endpoint changes.

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
| PHP | Files, classes, methods | Class definitions, imports, inheritance, static class usage, resolved static method calls, unresolved static-method warnings, object construction, literal includes |
| Routes | HTTP route declarations | Route to controller and controller method |
| SQL migrations | Migration files, tables | Tables created or altered and foreign-key references |
| PHP Models | Literal SQL fragments in model source | Model file to tables read by SELECT/FROM/JOIN and tables written by INSERT/REPLACE/UPDATE/DELETE/TRUNCATE |
| JavaScript and PHP views | JS files, API-call sites, PHP form/data attributes | Relative JS imports, scripts loaded by PHP views, form actions and data-* endpoint values, fetch/XHR calls resolved through those view-provided URLs to matching HTTP routes |

Node IDs are stable for the same node type and source key. Output ordering is deterministic, so graph diffs remain reviewable. The scanner also reports duplicate three-digit migration prefixes as a manual-review warning. In this repository, the two `002_` files are sequential in the documented fresh-install path, not alternatives; the exact sequence is in `database/FRESH_INSTALL.md` and is exercised by the disposable-database CI workflow. Graphify never decides migration order or applied state.

## Reading the result

Open .graphify/architecture.mmd in a Mermaid-compatible Markdown viewer. Use .graphify/graph.json for programmatic queries or later visualization tools. Each edge records its relationship type, for example routes_to_controller, uses_class, calls_method, creates_table, foreign_key_references, queries_table, writes_table, calls_route, or submits_to_route. A known in-repository class called through a static method name that cannot be found in that class or its indexed parent classes generates a warning. Graph statistics also report SQL fragments inspected and JavaScript API calls found, linked, or left unresolved.

## Accuracy boundaries

This is static source analysis, not a PHP or JavaScript compiler and not a runtime call trace. It does not guess dynamic class names, reflection, variable route construction, arbitrary SQL assembled at runtime, framework magic, or all instance method-to-method calls. Static method resolution follows indexed in-repository parent classes; traits, magic methods, external packages, and dynamic dispatch may remain unresolved. SQL analysis only recognizes table identifiers present in literal SQL strings inside model files; queries assembled from fragments, passed through helper methods, stored in external files, or generated at runtime may be missed. JavaScript route linking only succeeds when the request URL and HTTP method are statically resolvable and match a declared PHP route. Calls built from DOM form actions and data attributes are resolved when the same PHP view statically supplies the URL and loads the associated script. Simple concatenations that combine a known data-* base URL with literal path fragments are also resolved. Other calls built from runtime-only DOM values, unknown base-path variables, or dynamically generated strings are retained as api_call nodes with dynamic/unmatched resolution rather than being guessed. The scanner is intentionally conservative: a missing edge means “not detected by this scanner,” not proof that no runtime dependency exists.

Use the graph to navigate and prioritize code review, then verify important paths against source and tests. It does not replace integration testing, security review, or schema inspection.

## Safety boundaries

- The scanner reads source trees under app, bin, config, public, tests, and database/migrations.
- It explicitly skips storage, vendor, node_modules, and Git internals.
- It does not read .env, database credentials, private uploads, or production data.
- It does not execute migrations or the account-retention worker.
- Technical posts, attachments, schematics, diagnostic history, and other application data are never modified by Graphify.
