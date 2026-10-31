<?php
declare(strict_types=1);

use App\Core\App;

$root = dirname(__DIR__);
require $root . '/app/core/App.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This schema comparison tool is CLI-only.\n");
    exit(2);
}

$targetSchema = '';
$includeRows = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        echo "Usage: DB_NAME=source_database php bin/compare-database-schema.php --compare-to=restored_database [--rows]\n";
        echo "Read-only comparison of tables, columns, indexes, foreign keys, CHECK constraints, triggers, and optionally row counts.\n";
        exit(0);
    }
    if (str_starts_with($argument, '--compare-to=')) {
        $targetSchema = substr($argument, strlen('--compare-to='));
        continue;
    }
    if ($argument === '--rows') {
        $includeRows = true;
        continue;
    }
    fwrite(STDERR, "Unknown option: {$argument}\n");
    exit(2);
}

try {
    // Load only the database settings. Do not boot the web application here:
    // App::boot() also runs log retention and registers request handlers.
    $envFile = $root . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '' || getenv($key) !== false || array_key_exists($key, $_ENV)) {
                continue;
            }
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            $_ENV[$key] = $value;
        }
    }

    $database = require $root . '/config/database.php';
    $sourceSchema = (string) ($database['name'] ?? '');

    $validSchema = static function (string $schema): bool {
        return $schema !== ''
            && strlen($schema) <= 64
            && preg_match('/^[A-Za-z0-9_]+$/D', $schema) === 1;
    };

    if (!$validSchema($sourceSchema) || !$validSchema($targetSchema)) {
        throw new InvalidArgumentException('Source or target database name is invalid. Use letters, digits, and underscores only.');
    }
    if ($sourceSchema === $targetSchema) {
        throw new InvalidArgumentException('Source and target databases must be different.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=utf8mb4',
        (string) ($database['host'] ?? '127.0.0.1'),
        (int) ($database['port'] ?? 3306)
    );
    $pdo = new PDO(
        $dsn,
        (string) ($database['user'] ?? ''),
        (string) ($database['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $schemaExists = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?'
    );
    foreach ([$sourceSchema, $targetSchema] as $schemaName) {
        $schemaExists->execute([$schemaName]);
        if ((int) $schemaExists->fetchColumn() !== 1) {
            throw new RuntimeException('A source or target database does not exist.');
        }
    }

    $fetchRows = static function (string $sql, string $schema) use ($pdo): array {
        $statement = $pdo->prepare($sql);
        $statement->execute([$schema]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    };

    $sections = [
        'tables' => [
            'sql' => 'SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation
                      FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'
                      ORDER BY TABLE_NAME',
            'keys' => ['table_name'],
        ],
        'views' => [
            'sql' => 'SELECT TABLE_NAME AS view_name, VIEW_DEFINITION AS view_definition,
                             CHECK_OPTION AS check_option, IS_UPDATABLE AS is_updatable,
                             DEFINER AS definer, SECURITY_TYPE AS security_type
                      FROM information_schema.VIEWS
                      WHERE TABLE_SCHEMA = ?
                      ORDER BY TABLE_NAME',
            'keys' => ['view_name'],
        ],
        'columns' => [
            'sql' => 'SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
                             ORDINAL_POSITION AS ordinal_position, COLUMN_TYPE AS column_type,
                             IS_NULLABLE AS is_nullable, COLUMN_DEFAULT AS column_default,
                             EXTRA AS extra, CHARACTER_SET_NAME AS character_set_name,
                             COLLATION_NAME AS collation_name, GENERATION_EXPRESSION AS generation_expression
                      FROM information_schema.COLUMNS
                      WHERE TABLE_SCHEMA = ?
                      ORDER BY TABLE_NAME, ORDINAL_POSITION',
            'keys' => ['table_name', 'column_name'],
        ],
        'indexes' => [
            'sql' => 'SELECT TABLE_NAME AS table_name, INDEX_NAME AS index_name,
                             NON_UNIQUE AS non_unique, SEQ_IN_INDEX AS sequence_number,
                             COLUMN_NAME AS column_name, SUB_PART AS sub_part,
                             COLLATION AS collation, INDEX_TYPE AS index_type
                      FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = ?
                      ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            'keys' => ['table_name', 'index_name', 'sequence_number'],
        ],
        'foreign_keys' => [
            'sql' => 'SELECT k.TABLE_NAME AS table_name, k.CONSTRAINT_NAME AS constraint_name,
                             k.COLUMN_NAME AS column_name, k.ORDINAL_POSITION AS ordinal_position,
                             k.REFERENCED_TABLE_NAME AS referenced_table_name,
                             k.REFERENCED_COLUMN_NAME AS referenced_column_name,
                             r.UPDATE_RULE AS update_rule, r.DELETE_RULE AS delete_rule
                      FROM information_schema.KEY_COLUMN_USAGE k
                      LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                        ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                       AND r.TABLE_NAME = k.TABLE_NAME
                       AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                      WHERE k.CONSTRAINT_SCHEMA = ?
                        AND k.REFERENCED_TABLE_NAME IS NOT NULL
                      ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            'keys' => ['table_name', 'constraint_name', 'ordinal_position'],
        ],
        'check_constraints' => [
            'sql' => 'SELECT tc.TABLE_NAME AS table_name, tc.CONSTRAINT_NAME AS constraint_name,
                             cc.CHECK_CLAUSE AS check_clause
                      FROM information_schema.TABLE_CONSTRAINTS tc
                      INNER JOIN information_schema.CHECK_CONSTRAINTS cc
                        ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
                       AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
                      WHERE tc.CONSTRAINT_SCHEMA = ? AND tc.CONSTRAINT_TYPE = \'CHECK\'
                      ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME',
            'keys' => ['table_name', 'constraint_name'],
        ],
        'triggers' => [
            'sql' => 'SELECT TRIGGER_NAME AS trigger_name, EVENT_MANIPULATION AS event_manipulation,
                             EVENT_OBJECT_TABLE AS event_object_table, ACTION_TIMING AS action_timing,
                             ACTION_STATEMENT AS action_statement
                      FROM information_schema.TRIGGERS
                      WHERE TRIGGER_SCHEMA = ?
                      ORDER BY TRIGGER_NAME',
            'keys' => ['trigger_name'],
        ],
        'routines' => [
            'sql' => 'SELECT ROUTINE_TYPE AS routine_type, ROUTINE_NAME AS routine_name,
                             DATA_TYPE AS data_type, DTD_IDENTIFIER AS dtd_identifier,
                             ROUTINE_DEFINITION AS routine_definition, SQL_DATA_ACCESS AS sql_data_access,
                             SECURITY_TYPE AS security_type, SQL_MODE AS sql_mode, DEFINER AS definer
                      FROM information_schema.ROUTINES
                      WHERE ROUTINE_SCHEMA = ?
                      ORDER BY ROUTINE_TYPE, ROUTINE_NAME',
            'keys' => ['routine_type', 'routine_name'],
        ],
        'events' => [
            'sql' => 'SELECT EVENT_NAME AS event_name, DEFINER AS definer, TIME_ZONE AS time_zone,
                             EVENT_TYPE AS event_type, EXECUTE_AT AS execute_at,
                             INTERVAL_VALUE AS interval_value, INTERVAL_FIELD AS interval_field,
                             SQL_MODE AS sql_mode, STATUS AS status,
                             ON_COMPLETION AS on_completion, EVENT_DEFINITION AS event_definition
                      FROM information_schema.EVENTS
                      WHERE EVENT_SCHEMA = ?
                      ORDER BY EVENT_NAME',
            'keys' => ['event_name'],
        ],
    ];

    $keyRows = static function (array $rows, array $keyFields): array {
        $map = [];
        foreach ($rows as $row) {
            $identity = [];
            foreach ($keyFields as $field) {
                $identity[] = $row[$field] ?? null;
            }
            $key = json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $value = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $map[$key] = is_string($value) ? $value : '';
        }
        ksort($map);
        return $map;
    };

    $allMatched = true;
    echo "Read-only schema comparison: {$sourceSchema} ↔ {$targetSchema}\n";

    $sourceTables = [];
    foreach ($sections as $name => $definition) {
        $sourceRows = $fetchRows($definition['sql'], $sourceSchema);
        $targetRows = $fetchRows($definition['sql'], $targetSchema);
        $sourceMap = $keyRows($sourceRows, $definition['keys']);
        $targetMap = $keyRows($targetRows, $definition['keys']);

        $sourceOnly = array_diff_key($sourceMap, $targetMap);
        $targetOnly = array_diff_key($targetMap, $sourceMap);
        $changed = [];
        foreach (array_intersect_key($sourceMap, $targetMap) as $key => $sourceValue) {
            if ($sourceValue !== $targetMap[$key]) {
                $changed[] = $key;
            }
        }

        if ($sourceOnly !== [] || $targetOnly !== [] || $changed !== []) {
            $allMatched = false;
            printf(
                "MISMATCH %-18s source-only=%d target-only=%d changed=%d\n",
                $name,
                count($sourceOnly),
                count($targetOnly),
                count($changed)
            );
            foreach (array_slice(array_keys($sourceOnly), 0, 5) as $key) {
                echo "  only in source: {$key}\n";
            }
            foreach (array_slice(array_keys($targetOnly), 0, 5) as $key) {
                echo "  only in target: {$key}\n";
            }
            foreach (array_slice($changed, 0, 5) as $key) {
                echo "  differs: {$key}\n";
            }
        } else {
            printf("MATCH    %-18s rows=%d\n", $name, count($sourceRows));
        }

        if ($name === 'tables') {
            $sourceTables = $sourceRows;
        }
    }

    if ($includeRows) {
        $rowCounts = static function (string $schema, array $tables) use ($pdo): array {
            $counts = [];
            foreach ($tables as $table) {
                $tableName = (string) $table['table_name'];
                $quotedSchema = str_replace('`', '``', $schema);
                $quotedTable = str_replace('`', '``', $tableName);
                $counts[$tableName] = (int) $pdo
                    ->query("SELECT COUNT(*) FROM `{$quotedSchema}`.`{$quotedTable}`")
                    ->fetchColumn();
            }
            ksort($counts);
            return $counts;
        };

        $sourceCounts = $rowCounts($sourceSchema, $sourceTables);
        $targetTableRows = $fetchRows($sections['tables']['sql'], $targetSchema);
        $targetCounts = $rowCounts($targetSchema, $targetTableRows);

        if ($sourceCounts !== $targetCounts) {
            $allMatched = false;
            echo "MISMATCH row_counts\n";
            $tableNames = array_unique(array_merge(array_keys($sourceCounts), array_keys($targetCounts)));
            sort($tableNames);
            $printed = 0;
            foreach ($tableNames as $tableName) {
                $sourceCount = $sourceCounts[$tableName] ?? null;
                $targetCount = $targetCounts[$tableName] ?? null;
                if ($sourceCount !== $targetCount) {
                    echo "  {$tableName}: source=" . var_export($sourceCount, true)
                        . " target=" . var_export($targetCount, true) . "\n";
                    if (++$printed >= 20) {
                        echo "  further row-count differences omitted\n";
                        break;
                    }
                }
            }
        } else {
            printf("MATCH    %-18s tables=%d\n", 'row_counts', count($sourceCounts));
        }
    }

    if (!$allMatched) {
        echo "Schema/data comparison found differences. No database objects or rows were modified.\n";
        exit(1);
    }

    echo "Schema comparison passed. No database objects or rows were modified.\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Schema comparison failed (' . $error::class . '). No write operation was attempted.\n');
    exit(2);
}
