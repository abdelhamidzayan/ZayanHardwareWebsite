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


if __name__ == "__main__":
    unittest.main(verbosity=2)
