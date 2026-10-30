#!/usr/bin/env python3
"""Build a deterministic, dependency-free static dependency graph for Zayan Hardware."""
from __future__ import annotations
import argparse
import hashlib
import json
import re
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any

ROOT_DIRS = ("app", "bin", "config", "public", "tests", "database/migrations")
SKIP_DIRS = {"vendor", ".git", "node_modules", "storage", ".graphify", "cache"}
SOURCE_SUFFIXES = {".php", ".js", ".sql"}

def stable_id(kind: str, key: str) -> str:
    digest = hashlib.sha1(f"{kind}:{key}".encode("utf-8")).hexdigest()[:12]
    return f"{kind}_{digest}"

def clean_label(value: str) -> str:
    return value.replace('"', "'").replace("\n", " ").replace("\r", " ")

class Graph:
    """Conservative static graph; application code is never executed."""
    def __init__(self, root: Path) -> None:
        self.root = root.resolve()
        self.nodes: dict[str, dict[str, Any]] = {}
        self.edges: dict[tuple[str, str, str], dict[str, Any]] = {}
        self.warnings: list[str] = []
        self.php_classes: dict[str, str] = {}
        self.files: list[Path] = []
        self.route_count = 0
        self.route_lookup: dict[tuple[str, str], str] = {}
        self.sql_queries_analyzed = 0
        self.api_calls_found = 0
        self.api_calls_linked = 0
        self.api_calls_unresolved = 0

    def add_node(self, kind: str, key: str, label: str, path: str | None = None, **attrs: Any) -> str:
        node_id = stable_id(kind, key)
        node = {"id": node_id, "kind": kind, "key": key, "label": label}
        if path is not None:
            node["path"] = path
        node.update({k: v for k, v in attrs.items() if v is not None})
        self.nodes[node_id] = node
        return node_id

    def add_edge(self, source: str, target: str, kind: str) -> None:
        if source != target:
            self.edges[(source, target, kind)] = {"source": source, "target": target, "kind": kind}

    def relative(self, path: Path) -> str:
        return path.resolve().relative_to(self.root).as_posix()

    def discover(self) -> None:
        for directory in ROOT_DIRS:
            base = self.root / directory
            if not base.exists():
                continue
            for path in base.rglob("*"):
                if path.is_file() and path.suffix.lower() in SOURCE_SUFFIXES:
                    if not any(part in SKIP_DIRS for part in path.parts):
                        self.files.append(path)
        self.files.sort(key=lambda p: self.relative(p))
        for path in self.files:
            rel = self.relative(path)
            self.add_node("file", rel, rel, path=rel, extension=path.suffix.lower())

    @staticmethod
    def php_namespace(source: str) -> str:
        match = re.search(r"^\s*namespace\s+([A-Za-z_][\\A-Za-z0-9_]*)\s*;", source, re.M)
        return match.group(1) if match else ""

    @staticmethod
    def php_imports(source: str) -> dict[str, str]:
        imports: dict[str, str] = {}
        pattern = r"^\s*use\s+(?!function\b|const\b)([A-Za-z_\\][\\A-Za-z0-9_]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;"
        for match in re.finditer(pattern, source, re.M):
            full = match.group(1).lstrip("\\")
            imports[match.group(2) or full.rsplit("\\", 1)[-1]] = full
        return imports

    def index_php_declarations(self, path: Path, source: str) -> None:
        namespace = self.php_namespace(source)
        rel = self.relative(path)
        for match in re.finditer(r"\b(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)", source):
            name = match.group(2)
            fqn = f"{namespace}\\{name}" if namespace else name
            self.php_classes[fqn] = self.add_node("class", fqn, fqn, path=rel, declaration=match.group(1))

    def analyze_php(self, path: Path, source: str) -> None:
        rel = self.relative(path)
        file_id = stable_id("file", rel)
        namespace = self.php_namespace(source)
        imports = self.php_imports(source)
        matches = list(re.finditer(r"\b(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)", source))
        classes: list[dict[str, str]] = []
        for index, match in enumerate(matches):
            short = match.group(2)
            fqn = f"{namespace}\\{short}" if namespace else short
            class_id = self.php_classes[fqn]
            classes.append({"fqn": fqn, "short": short, "id": class_id})
            self.add_edge(class_id, file_id, "defined_in")
            next_class = matches[index + 1].start() if index + 1 < len(matches) else len(source)
            body_end = source.find("\n}", match.end(), next_class)
            class_source = source[match.start():body_end if body_end >= 0 else next_class]
            for method in re.finditer(r"\bfunction\s+&?\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(", class_source):
                name = method.group(1)
                method_id = self.add_node("method", f"{fqn}::{name}", f"{short}::{name}", path=rel, class_name=fqn)
                self.add_edge(class_id, method_id, "defines_method")

        owner_id = classes[0]["id"] if len(classes) == 1 else file_id

        def resolve_class(token: str) -> str:
            token = token.lstrip("\\")
            short = token.rsplit("\\", 1)[-1]
            return imports.get(token, imports.get(short, token if "\\" in token else (f"{namespace}\\{token}" if namespace else token)))

        for data in classes:
            for clause in re.finditer(r"\b(?:extends|implements)\s+([\\A-Za-z_][\\A-Za-z0-9_,\\\s]*)", source):
                for name in re.split(r",|\s+", clause.group(1).strip()):
                    name = name.strip().lstrip("\\")
                    if name and name not in {"extends", "implements"}:
                        target = self.php_classes.get(resolve_class(name))
                        if target:
                            self.add_edge(data["id"], target, "extends_or_implements")
            for imported in imports.values():
                target = self.php_classes.get(imported)
                if target:
                    self.add_edge(data["id"], target, "imports_class")

        for match in re.finditer(r"(?<![A-Za-z0-9_$])([\\A-Z][\\A-Za-z0-9_]*)(?=::|\s*\()", source):
            target = self.php_classes.get(resolve_class(match.group(1)))
            if target and target != owner_id:
                self.add_edge(owner_id, target, "uses_class")
        for match in re.finditer(r"\bnew\s+([\\A-Za-z_][\\A-Za-z0-9_]*)", source):
            target = self.php_classes.get(resolve_class(match.group(1)))
            if target and target != owner_id:
                self.add_edge(owner_id, target, "instantiates")

        include_pattern = r"\b(?:require|require_once|include|include_once)\s*\(?\s*['\"]([^'\"]+\.php)['\"]"
        for match in re.finditer(include_pattern, source):
            included = (path.parent / match.group(1)).resolve()
            if included.is_file() and included.is_relative_to(self.root):
                self.add_edge(file_id, stable_id("file", self.relative(included)), "includes_file")

    def analyze_routes(self, path: Path, source: str) -> None:
        imports = self.php_imports(source)
        file_id = stable_id("file", self.relative(path))
        pattern = re.compile(r"\$router->(get|post)\(\s*(['\"])(.*?)\2\s*,\s*\[\s*([A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*(['\"])([A-Za-z_][A-Za-z0-9_]*)\5\s*\]\s*\)", re.S)
        for match in pattern.finditer(source):
            verb, route_path, controller, method = match.group(1).upper(), match.group(3), match.group(4), match.group(6)
            controller_fqn = imports.get(controller, controller)
            route_key = f"{verb} {route_path}"
            route_id = self.add_node("route", route_key, route_key, path=self.relative(path), controller=controller_fqn, method=method)
            self.add_edge(file_id, route_id, "declares_route")
            self.route_lookup[(verb, route_path)] = route_id
            self.route_count += 1
            controller_id = self.php_classes.get(controller_fqn)
            if controller_id:
                self.add_edge(route_id, controller_id, "routes_to_controller")
            method_id = stable_id("method", f"{controller_fqn}::{method}")
            if method_id in self.nodes:
                self.add_edge(route_id, method_id, "routes_to_method")

    def analyze_sql(self, path: Path, source: str) -> None:
        rel = self.relative(path)
        file_id = stable_id("file", rel)
        table_token = r"[\x60\"]?([A-Za-z_][A-Za-z0-9_]*)[\x60\"]?"
        create = re.compile(r"\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?" + table_token + r"\s*\((.*?)\)\s*ENGINE\s*=", re.I | re.S)
        alter = re.compile(r"\bALTER\s+TABLE\s+" + table_token + r"\s+(.*?);", re.I | re.S)
        references = re.compile(r"\bREFERENCES\s+" + table_token, re.I)

        def table(name: str, action: str) -> str:
            table_id = self.add_node("table", name.lower(), name.lower(), path=rel)
            self.add_edge(file_id, table_id, action)
            return table_id

        for match in create.finditer(source):
            source_id = table(match.group(1), "creates_table")
            for reference in references.finditer(match.group(2)):
                name = reference.group(1).lower()
                self.add_edge(source_id, self.add_node("table", name, name), "foreign_key_references")
        for match in alter.finditer(source):
            source_id = table(match.group(1), "alters_table")
            for reference in references.finditer(match.group(2)):
                name = reference.group(1).lower()
                self.add_edge(source_id, self.add_node("table", name, name), "foreign_key_references")
        for name in set(re.findall(r"\bCREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?" + table_token, source, re.I)):
            self.add_node("table", name.lower(), name.lower(), path=rel)

        prefix = Path(rel).name.split("_", 1)[0]
        if re.fullmatch(r"\d{3}", prefix):
            self.add_node("migration", rel, Path(rel).name, path=rel, sequence=prefix)

    def analyze_js(self, path: Path, source: str) -> None:
        file_id = stable_id("file", self.relative(path))
        pattern = r"\b(?:import\s+(?:[^;]*?\s+from\s+)?|export\s+[^;]*?\s+from\s+)['\"]([^'\"]+)['\"]"
        for match in re.finditer(pattern, source):
            specifier = match.group(1)
            if not specifier.startswith("."):
                continue
            target = (path.parent / specifier).resolve()
            candidates = (target, target.with_suffix(".js"), target / "index.js")
            found = next((item for item in candidates if item.is_file() and item.is_relative_to(self.root)), None)
            if found:
                self.add_edge(file_id, stable_id("file", self.relative(found)), "imports_js_module")


    def analyze_model_sql(self, path: Path, source: str) -> None:
        """Conservatively map literal SQL fragments in models to table nodes."""
        rel = self.relative(path)
        file_id = stable_id("file", rel)
        literal = re.compile(r"""(['"])((?:\\.|(?!\1)[\s\S])*)\1""")
        table_token = r"(?:[A-Za-z_][A-Za-z0-9_$]*\.)?([A-Za-z_][A-Za-z0-9_$]*)"
        reads: set[str] = set()
        writes: set[str] = set()

        for match in literal.finditer(source):
            query = match.group(2)
            if not re.search(r"\b(SELECT|INSERT|UPDATE|DELETE|REPLACE|TRUNCATE)\b", query, re.I):
                continue
            self.sql_queries_analyzed += 1
            for found in re.finditer(r"\b(?:FROM|JOIN)\s+" + table_token, query, re.I):
                reads.add(found.group(1).lower())
            write_patterns = (
                r"\bINSERT\s+(?:IGNORE\s+)?INTO\s+" + table_token,
                r"\bREPLACE\s+INTO\s+" + table_token,
                r"\bUPDATE\s+" + table_token,
                r"\bDELETE\s+FROM\s+" + table_token,
                r"\bTRUNCATE\s+(?:TABLE\s+)?" + table_token,
            )
            for pattern in write_patterns:
                for found in re.finditer(pattern, query, re.I):
                    writes.add(found.group(1).lower())

        for name in sorted(reads - writes):
            table_id = self.add_node("table", name, name)
            self.add_edge(file_id, table_id, "queries_table")
        for name in sorted(writes):
            table_id = self.add_node("table", name, name)
            self.add_edge(file_id, table_id, "writes_table")

    @staticmethod
    def js_call_arguments(source: str, open_index: int) -> list[str]:
        """Split a JavaScript call's arguments without evaluating its expression."""
        stack = ["("]
        quote: str | None = None
        escaped = False
        end = len(source)
        for index in range(open_index + 1, len(source)):
            char = source[index]
            if quote is not None:
                if escaped:
                    escaped = False
                elif char == "\\":
                    escaped = True
                elif char == quote:
                    quote = None
                continue
            if char in ("'", '"', chr(96)):
                quote = char
            elif char in "([{":
                stack.append(char)
            elif char in ")]}":
                if stack:
                    stack.pop()
                if not stack:
                    end = index
                    break
        body = source[open_index + 1:end]
        args: list[str] = []
        start = 0
        stack = []
        quote = None
        escaped = False
        for index, char in enumerate(body):
            if quote is not None:
                if escaped:
                    escaped = False
                elif char == "\\":
                    escaped = True
                elif char == quote:
                    quote = None
                continue
            if char in ("'", '"', chr(96)):
                quote = char
            elif char in "([{":
                stack.append(char)
            elif char in ")]}":
                if stack:
                    stack.pop()
            elif char == "," and not stack:
                args.append(body[start:index].strip())
                start = index + 1
        final = body[start:].strip()
        if final or args:
            args.append(final)
        return args

    @staticmethod
    def js_static_path(expression: str, source: str) -> str | None:
        expression = expression.strip()
        literal_match = re.fullmatch(r"""(['"])(.*?)\1""", expression, re.S)
        if literal_match:
            target = literal_match.group(2)
        elif expression.startswith(chr(96)) and expression.endswith(chr(96)):
            target = re.sub(r"\$\{[^}]*\}", "{dynamic}", expression[1:-1])
        elif re.fullmatch(r"[A-Za-z_$][A-Za-z0-9_$]*", expression):
            variable = re.escape(expression)
            assignment = re.search(
                r"\b(?:const|let|var)\s+" + variable + r"\s*=\s*(['\"])(.*?)\1",
                source, re.S,
            )
            if assignment:
                target = assignment.group(2)
            else:
                replacement_path = re.search(
                    r"\b(?:const|let|var)\s+" + variable
                    + r"\s*=\s*[^;]*?\.replace\s*\([^,]+,\s*(['\"])(/[^'\"]+)\1",
                    source, re.S,
                )
                if not replacement_path:
                    return None
                target = replacement_path.group(2)
        else:
            return None
        target = target.split("?", 1)[0].split("#", 1)[0]
        return target if target.startswith("/") else None

    def matching_route(self, verb: str | None, target: str) -> str | None:
        candidates: list[str] = []
        for (route_verb, route_path), route_id in self.route_lookup.items():
            if verb is not None and route_verb != verb:
                continue
            pieces = re.split(r"(\{[A-Za-z_][A-Za-z0-9_]*\})", route_path)
            regex = "".join(
                "[^/]+" if re.fullmatch(r"\{[A-Za-z_][A-Za-z0-9_]*\}", part) else re.escape(part)
                for part in pieces
            )
            if re.fullmatch(regex, target):
                candidates.append(route_id)
        return candidates[0] if len(candidates) == 1 else None

    def analyze_js_api(self, path: Path, source: str) -> None:
        """Connect statically resolvable fetch() calls to PHP route declarations."""
        rel = self.relative(path)
        file_id = stable_id("file", rel)
        patterns = (
            (re.compile(r"\bfetch\s*\("), "fetch"),
            (re.compile(r"\b(?:xhr|xmlHttpRequest|httpRequest)\.open\s*\(", re.I), "xhr"),
        )
        for pattern, kind in patterns:
            for match in pattern.finditer(source):
                open_index = source.find("(", match.start())
                args = self.js_call_arguments(source, open_index)
                if not args:
                    continue
                if kind == "xhr":
                    method_match = re.fullmatch(r"""(['"])(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\1""", args[0], re.I)
                    verb = method_match.group(2).upper() if method_match else None
                    expression = args[1] if len(args) > 1 else ""
                else:
                    expression = args[0]
                    options = ",".join(args[1:])
                    method_match = re.search(r"\bmethod\s*:\s*(['\"])(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\1", options, re.I)
                    if method_match:
                        verb = method_match.group(2).upper()
                    elif re.search(r"\bmethod\s*:", options):
                        verb = None
                    else:
                        verb = "GET"
                self.api_calls_found += 1
                target = self.js_static_path(expression, source)
                route_id = self.matching_route(verb, target) if target else None
                line = source.count("\n", 0, match.start()) + 1
                call_key = f"{rel}:{line}:{kind}:{self.api_calls_found}"
                api_id = self.add_node(
                    "api_call", call_key,
                    f"{verb or 'METHOD?'} {target or 'dynamic URL'}",
                    path=rel, line=line, verb=verb,
                    target=target, expression=re.sub(r"\s+", " ", expression)[:180],
                    resolution="linked" if route_id else ("unmatched" if target else "dynamic"),
                )
                self.add_edge(file_id, api_id, "calls_api")
                if route_id:
                    self.add_edge(api_id, route_id, "calls_route")
                    self.api_calls_linked += 1
                else:
                    self.api_calls_unresolved += 1

    def analyze_view_scripts(self, path: Path, source: str) -> None:
        file_id = stable_id("file", self.relative(path))
        pattern = r"<script\b[^>]*\bsrc\s*=\s*['\"][^'\"]*/js/([^/'\"]+\.js)(?:\?[^'\"]*)?['\"]"
        for match in re.finditer(pattern, source, re.I):
            target_rel = f"public/js/{match.group(1)}"
            if (self.root / target_rel).is_file():
                self.add_edge(file_id, stable_id("file", target_rel), "loads_script")


    @staticmethod
    def php_attribute_path(value: str) -> str | None:
        path = re.sub(r"<\?.*?\?>", "{dynamic}", value, flags=re.S).strip()
        path = re.sub(r"^\{dynamic\}", "", path)
        path = re.sub(r"^https?://[^/]+", "", path)
        return path if path.startswith("/") else None

    @staticmethod
    def js_concat_path(expression: str, values: dict[str, str]) -> str | None:
        expression = expression.strip()
        if not expression:
            return None
        tokens = re.split(r"\s*\+\s*", expression)
        pieces: list[str] = []
        for token in tokens:
            token = token.strip().strip("() ")
            if token in values:
                pieces.append(values[token])
                continue
            literal = re.fullmatch(r"""(['"])(.*?)\1""", token, re.S)
            if literal:
                pieces.append(literal.group(2))
            elif token:
                pieces.append("{dynamic}")
        path = "".join(pieces)
        path = re.sub(r"^\{dynamic\}", "", path)
        path = path.split("?", 1)[0].split("#", 1)[0]
        return path if path.startswith("/") else None

    def analyze_view_api(self, path: Path, source: str, sources: dict[Path, str]) -> None:
        """Resolve JS endpoint variables from the PHP view that loads each script."""
        rel = self.relative(path)
        view_id = stable_id("file", rel)
        form_actions: list[tuple[str, str]] = []

        for action_match in re.finditer(r"\baction\s*=\s*(['\"])(.*?)\1", source, re.I | re.S):
            target = self.php_attribute_path(action_match.group(2))
            if not target:
                continue
            form_start = source.rfind("<form", 0, action_match.start())
            tag_prefix = source[form_start:action_match.start()] if form_start >= 0 else ""
            method_match = re.search(r"\bmethod\s*=\s*(['\"])(GET|POST|PUT|PATCH|DELETE)\1", tag_prefix, re.I)
            verb = method_match.group(2).upper() if method_match else "GET"
            route_id = self.matching_route(verb, target)
            if route_id:
                form_actions.append((target, route_id))
                self.add_edge(view_id, route_id, "submits_to_route")

        data_paths: dict[str, str] = {}
        for attr_match in re.finditer(r"\bdata-([a-z0-9-]+)\s*=\s*(['\"])(.*?)\2", source, re.I | re.S):
            name = attr_match.group(1)
            target = self.php_attribute_path(attr_match.group(3))
            if not target:
                continue
            parts = name.split("-")
            property_name = parts[0] + "".join(part[:1].upper() + part[1:] for part in parts[1:])
            data_paths[property_name] = target
            default_verb = "POST" if any(word in name for word in ("save-url", "suggest-url")) else "GET"
            route_id = self.matching_route(default_verb, target)
            if route_id:
                self.add_edge(view_id, route_id, "configures_route")

        source_by_rel = {self.relative(file_path): text for file_path, text in sources.items()}
        for edge in list(self.edges.values()):
            if edge["kind"] != "loads_script" or edge["source"] != view_id:
                continue
            script_node = self.nodes.get(edge["target"])
            if not script_node or "path" not in script_node:
                continue
            script_rel = script_node["path"]
            script_source = source_by_rel.get(script_rel, "")
            if not script_source:
                continue
            values = dict(data_paths)
            for assignment in re.finditer(
                r"\b(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*[^;\n]*?\.dataset\.([A-Za-z_$][A-Za-z0-9_$]*)",
                script_source,
            ):
                if assignment.group(2) in data_paths:
                    values[assignment.group(1)] = data_paths[assignment.group(2)]

            # A fetch(form.action) inherits the action of the form on this page.
            if len(form_actions) == 1 and re.search(r"\bform\.action\b", script_source):
                values["formAction"] = form_actions[0][0]

            for api_node in list(self.nodes.values()):
                if api_node["kind"] != "api_call" or api_node.get("path") != script_rel:
                    continue
                if api_node.get("resolution") == "linked":
                    continue
                expression = api_node.get("expression", "")
                if expression.strip() in ("form.action", "this.action") and "formAction" in values:
                    target = values["formAction"]
                elif any(re.search(r"\b" + re.escape(name) + r"\b", expression) for name in values):
                    target = self.js_concat_path(expression, values)
                else:
                    continue
                if not target:
                    continue
                route_id = self.matching_route(api_node.get("verb"), target)
                if not route_id:
                    continue
                api_node["target"] = target
                api_node["resolution"] = "linked"
                self.add_edge(api_node["id"], route_id, "calls_route")
                self.api_calls_linked += 1
                self.api_calls_unresolved = max(0, self.api_calls_unresolved - 1)

    def build(self) -> None:
        self.discover()
        sources: dict[Path, str] = {}
        for path in self.files:
            try:
                sources[path] = path.read_text(encoding="utf-8")
            except (UnicodeDecodeError, OSError) as exc:
                self.warnings.append(f"Could not read {self.relative(path)}: {exc}")
        # Two passes keep dependencies independent of file ordering.
        for path, source in sources.items():
            if path.suffix.lower() == ".php":
                self.index_php_declarations(path, source)
        for path, source in sources.items():
            suffix = path.suffix.lower()
            if suffix == ".php":
                self.analyze_php(path, source)
                if self.relative(path).startswith("app/models/"):
                    self.analyze_model_sql(path, source)
                if self.relative(path) == "config/routes.php":
                    self.analyze_routes(path, source)
                self.analyze_view_scripts(path, source)
            elif suffix == ".js":
                self.analyze_js(path, source)
                self.analyze_js_api(path, source)
            elif suffix == ".sql":
                self.analyze_sql(path, source)

        source_by_rel = {self.relative(path): source for path, source in sources.items()}
        for path, source in sources.items():
            if self.relative(path).startswith("app/views/"):
                self.analyze_view_api(path, source, sources)

        by_sequence: dict[str, list[str]] = defaultdict(list)
        for node in self.nodes.values():
            if node["kind"] == "migration":
                by_sequence[node["sequence"]].append(node["path"])
        for sequence, paths in sorted(by_sequence.items()):
            if len(paths) > 1:
                self.warnings.append(f"Duplicate migration prefix {sequence}: {', '.join(sorted(paths))}. Confirm target schema history; do not blindly run both as an ordered pair.")

    def as_dict(self) -> dict[str, Any]:
        edges = sorted(self.edges.values(), key=lambda item: (item["kind"], item["source"], item["target"]))
        nodes = sorted(self.nodes.values(), key=lambda item: (item["kind"], item["key"]))
        node_counts, edge_counts = Counter(n["kind"] for n in nodes), Counter(e["kind"] for e in edges)
        return {
            "schema_version": 1,
            "generator": "Zayan Hardware Graphify (static source analyzer)",
            "root": ".",
            "analysis": "static; application code is not executed",
            "stats": {
                "files_scanned": len(self.files), "routes_found": self.route_count,
                "sql_queries_analyzed": self.sql_queries_analyzed,
                "api_calls_found": self.api_calls_found,
                "api_calls_linked": self.api_calls_linked,
                "api_calls_unresolved": self.api_calls_unresolved,
                "nodes": len(nodes), "edges": len(edges),
                "nodes_by_kind": dict(sorted(node_counts.items())),
                "edges_by_kind": dict(sorted(edge_counts.items())),
            },
            "warnings": sorted(set(self.warnings)),
            "nodes": nodes,
            "edges": edges,
        }

    def mermaid(self) -> str:
        data = self.as_dict()
        nodes = {node["id"]: node for node in data["nodes"]}
        keep = {"route", "class", "table"}
        selected = [
            edge for edge in data["edges"]
            if edge["kind"] in {
                "routes_to_controller", "routes_to_method", "foreign_key_references",
                "queries_table", "writes_table", "calls_route"
            }
        ][:320]
        used: set[str] = set()
        lines = ["flowchart LR"]
        for edge in selected:
            for node_id in (edge["source"], edge["target"]):
                if node_id not in used:
                    used.add(node_id)
                    node = nodes[node_id]
                    label = clean_label(node["label"])
                    shape = f'{node_id}{{"{label}"}}' if node["kind"] == "route" else f'{node_id}["{label}"]'
                    lines.append(f"    {shape}")
            lines.append(f"    {edge['source']} -->|{edge['kind']}| {edge['target']}")
        if not selected:
            lines.append('    Empty["No matching relationships detected"]')
        lines.extend(["", "%% Full graph, including file nodes, is in graph.json."])
        if self.warnings:
            lines.append("%% Warnings: " + " | ".join(clean_label(x) for x in sorted(set(self.warnings))))
        return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Generate a static dependency graph for Zayan Hardware.")
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[2], help="Project root")
    parser.add_argument("--output-dir", type=Path, default=Path(".graphify"), help="Output directory")
    parser.add_argument("--check", action="store_true", help="Fail if existing graph output is stale")
    args = parser.parse_args()
    root = args.root.resolve()
    output = args.output_dir if args.output_dir.is_absolute() else root / args.output_dir
    graph = Graph(root)
    graph.build()
    outputs = {
        output / "graph.json": json.dumps(graph.as_dict(), ensure_ascii=False, indent=2) + "\n",
        output / "architecture.mmd": graph.mermaid(),
    }
    changed = []
    for target, content in outputs.items():
        existing = target.read_text(encoding="utf-8") if target.is_file() else None
        if existing != content:
            changed.append(target.name)
            if not args.check:
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text(content, encoding="utf-8")
    if args.check and changed:
        print("Graphify output is stale: " + ", ".join(changed), file=sys.stderr)
        return 1
    print(json.dumps({"status": "ok", **graph.as_dict()["stats"], "warnings": graph.warnings}, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
