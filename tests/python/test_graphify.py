from __future__ import annotations

import sys
import tempfile
import unittest
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(PROJECT_ROOT / "tools" / "graphify"))
from graphify import Graph  # noqa: E402


class GraphifyTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        for folder in ("app/controllers", "app/services", "config", "public/js", "database/migrations"):
            (self.root / folder).mkdir(parents=True, exist_ok=True)

    def tearDown(self) -> None:
        self.temp.cleanup()

    def write(self, relative: str, content: str) -> None:
        target = self.root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8")

    def graph(self) -> Graph:
        graph = Graph(self.root)
        graph.build()
        return graph

    def test_resolves_php_dependencies_independent_of_file_order(self) -> None:
        self.write("app/controllers/ExampleController.php", """<?php
namespace App\\Controllers;
use App\\Services\\ExampleService;
final class ExampleController {
    public function index(): void { ExampleService::run(); }
}
""")
        self.write("app/services/ExampleService.php", """<?php
namespace App\\Services;
final class ExampleService { public static function run(): void {} }
""")
        graph = self.graph()
        controller = graph.php_classes["App\\Controllers\\ExampleController"]
        service = graph.php_classes["App\\Services\\ExampleService"]
        self.assertIn((controller, service, "imports_class"), graph.edges)
        self.assertIn((controller, service, "uses_class"), graph.edges)

    def test_maps_route_to_controller_and_method(self) -> None:
        self.write("app/controllers/ExampleController.php", """<?php
namespace App\\Controllers;
final class ExampleController { public function index(): void {} }
""")
        self.write("config/routes.php", """<?php
use App\\Controllers\\ExampleController;
$router->get('/example', [ExampleController::class, 'index']);
""")
        graph = self.graph()
        self.assertEqual(graph.route_count, 1)
        route_id = next(n["id"] for n in graph.nodes.values() if n["kind"] == "route")
        controller_id = graph.php_classes["App\\Controllers\\ExampleController"]
        method_id = next(n["id"] for n in graph.nodes.values()
                         if n["kind"] == "method" and n["key"] == "App\\Controllers\\ExampleController::index")
        self.assertIn((route_id, controller_id, "routes_to_controller"), graph.edges)
        self.assertIn((route_id, method_id, "routes_to_method"), graph.edges)

    def test_maps_foreign_key_from_the_correct_source_table(self) -> None:
        self.write("database/migrations/001_schema.sql", """CREATE TABLE parent (
 id INT PRIMARY KEY
) ENGINE=InnoDB;
CREATE TABLE child (
 id INT PRIMARY KEY,
 parent_id INT,
 CONSTRAINT fk_child_parent FOREIGN KEY (parent_id) REFERENCES parent(id)
) ENGINE=InnoDB;
""")
        graph = self.graph()
        parent = next(n["id"] for n in graph.nodes.values() if n["kind"] == "table" and n["key"] == "parent")
        child = next(n["id"] for n in graph.nodes.values() if n["kind"] == "table" and n["key"] == "child")
        self.assertIn((child, parent, "foreign_key_references"), graph.edges)

    def test_finds_javascript_local_imports(self) -> None:
        self.write("public/js/a.js", "import { value } from './b.js';\nconsole.log(value);\n")
        self.write("public/js/b.js", "export const value = 1;\n")
        graph = self.graph()
        a = next(n["id"] for n in graph.nodes.values() if n["kind"] == "file" and n["key"] == "public/js/a.js")
        b = next(n["id"] for n in graph.nodes.values() if n["kind"] == "file" and n["key"] == "public/js/b.js")
        self.assertIn((a, b, "imports_js_module"), graph.edges)

    def test_warns_about_duplicate_migration_prefixes(self) -> None:
        self.write("database/migrations/002_alpha.sql", "CREATE TABLE alpha (id INT) ENGINE=InnoDB;")
        self.write("database/migrations/002_beta.sql", "CREATE TABLE beta (id INT) ENGINE=InnoDB;")
        graph = self.graph()
        self.assertTrue(any("Duplicate migration prefix 002" in warning for warning in graph.warnings))


    def test_maps_model_sql_reads_and_writes_to_tables(self) -> None:
        self.write("app/models/IssueModel.php", """<?php
final class IssueModel {
    public function readAndWrite(): void {
        $this->db->prepare('SELECT i.id FROM issues i JOIN issue_replies r ON r.issue_id = i.id');
        $this->db->prepare('INSERT INTO issue_results (issue_id, was_repaired) VALUES (?, ?)');
    }
}
""")
        graph = self.graph()
        model_id = next(n["id"] for n in graph.nodes.values()
                        if n["kind"] == "file" and n["key"] == "app/models/IssueModel.php")
        issues = next(n["id"] for n in graph.nodes.values()
                      if n["kind"] == "table" and n["key"] == "issues")
        replies = next(n["id"] for n in graph.nodes.values()
                       if n["kind"] == "table" and n["key"] == "issue_replies")
        results = next(n["id"] for n in graph.nodes.values()
                       if n["kind"] == "table" and n["key"] == "issue_results")
        self.assertIn((model_id, issues, "queries_table"), graph.edges)
        self.assertIn((model_id, replies, "queries_table"), graph.edges)
        self.assertIn((model_id, results, "writes_table"), graph.edges)

    def test_maps_javascript_fetch_to_matching_php_route(self) -> None:
        self.write("app/controllers/ExampleController.php", """<?php
namespace App\\Controllers;
final class ExampleController { public function reply(): void {} }
""")
        self.write("config/routes.php", """<?php
use App\\Controllers\\ExampleController;
$router->post('/issues/{id}/reply', [ExampleController::class, 'reply']);
""")
        self.write("public/js/replies.js", """fetch('/issues/42/reply', { method: 'POST' });
""")
        graph = self.graph()
        route_id = next(n["id"] for n in graph.nodes.values() if n["kind"] == "route")
        api_id = next(n["id"] for n in graph.nodes.values() if n["kind"] == "api_call")
        js_id = next(n["id"] for n in graph.nodes.values()
                     if n["kind"] == "file" and n["key"] == "public/js/replies.js")
        self.assertEqual(graph.api_calls_linked, 1)
        self.assertIn((js_id, api_id, "calls_api"), graph.edges)
        self.assertIn((api_id, route_id, "calls_route"), graph.edges)

    def test_keeps_dynamic_javascript_api_calls_unresolved(self) -> None:
        self.write("public/js/dynamic.js", "fetch(apiBase + '/some/path');")
        graph = self.graph()
        api_node = next(n for n in graph.nodes.values() if n["kind"] == "api_call")
        self.assertEqual(api_node["resolution"], "dynamic")
        self.assertEqual(graph.api_calls_linked, 0)
        self.assertEqual(graph.api_calls_unresolved, 1)


    def test_resolves_js_api_variable_from_php_view_data_url(self) -> None:
        self.write("app/controllers/DeviceController.php", """<?php
namespace App\\Controllers;
final class DeviceController { public function models(): void {} }
""")
        self.write("config/routes.php", """<?php
use App\\Controllers\\DeviceController;
$router->get('/devices/models', [DeviceController::class, 'models']);
""")
        self.write("app/views/issues/create.php", """<form data-composer data-lookup-url="/devices/models"></form>
<script src="/js/composer.js" defer></script>
""")
        self.write("public/js/composer.js", """const form = document.querySelector('[data-composer]');
const lookupUrl = form.dataset.lookupUrl || '';
fetch(lookupUrl + '?q=phone', { credentials: 'same-origin' });
""")
        graph = self.graph()
        api_node = next(n for n in graph.nodes.values() if n["kind"] == "api_call")
        route_id = next(n["id"] for n in graph.nodes.values() if n["kind"] == "route")
        self.assertEqual(api_node["resolution"], "linked")
        self.assertEqual(api_node["target"], "/devices/models")
        self.assertIn((api_node["id"], route_id, "calls_route"), graph.edges)



if __name__ == "__main__":
    unittest.main(verbosity=2)
