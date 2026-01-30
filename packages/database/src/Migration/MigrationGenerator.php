<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;

use Marko\Database\Schema\Table;

/**
 * Generates migration PHP files from SchemaDiff objects.
 *
 * Uses nowdoc syntax for SQL statements to enable easy copy/paste
 * for testing in external database tools.
 */
class MigrationGenerator
{
    private const string MIGRATIONS_SUBDIR = 'database/migrations';

    private readonly string $basePath;

    private int $timestampOffset = 0;

    public function __construct(
        private readonly SqlGeneratorInterface $sqlGenerator,
        ProjectPaths $paths,
    ) {
        $this->basePath = $paths->base;
    }

    /**
     * Generate migration files from a schema diff.
     *
     * @return array<string> Paths to generated migration files
     */
    public function generate(
        SchemaDiff $diff,
    ): array {
        if ($diff->isEmpty()) {
            return [];
        }

        $this->ensureMigrationsDirectoryExists();
        $this->timestampOffset = 0;

        $paths = [];

        // Sort tables by foreign key dependencies before generating migrations
        $sortedTables = $this->sortTablesByDependencies($diff->tablesToCreate);

        // Generate separate migrations for each table creation (in dependency order)
        foreach ($sortedTables as $table) {
            $tableDiff = new SchemaDiff(tablesToCreate: [$table]);
            $upStatements = $this->sqlGenerator->generateUp($tableDiff);
            $downStatements = $this->sqlGenerator->generateDown($tableDiff);

            $filename = $this->generateFilename('create', $table->name);
            $content = $this->generateMigrationContent($upStatements, $downStatements);
            $path = $this->writeMigration($filename, $content);
            $paths[] = $path;
        }

        // Generate separate migrations for each table alteration
        foreach ($diff->tablesToAlter as $tableName => $tableDiff) {
            $alterDiff = new SchemaDiff(tablesToAlter: [$tableName => $tableDiff]);
            $upStatements = $this->sqlGenerator->generateUp($alterDiff);
            $downStatements = $this->sqlGenerator->generateDown($alterDiff);

            $filename = $this->generateFilename('alter', $tableName);
            $content = $this->generateMigrationContent($upStatements, $downStatements);
            $path = $this->writeMigration($filename, $content);
            $paths[] = $path;
        }

        // Generate separate migrations for each table drop (reverse dependency order)
        $sortedDropTables = $this->sortTablesByDependencies($diff->tablesToDrop);
        $reversedDropTables = array_reverse($sortedDropTables);

        foreach ($reversedDropTables as $table) {
            $tableDiff = new SchemaDiff(tablesToDrop: [$table]);
            $upStatements = $this->sqlGenerator->generateUp($tableDiff);
            $downStatements = $this->sqlGenerator->generateDown($tableDiff);

            $filename = $this->generateFilename('drop', $table->name);
            $content = $this->generateMigrationContent($upStatements, $downStatements);
            $path = $this->writeMigration($filename, $content);
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Sort tables by foreign key dependencies using topological sort.
     *
     * Tables with no dependencies come first, tables that depend on others come after.
     * Original input order is preserved for tables without dependencies between them.
     *
     * @param array<Table> $tables
     * @return array<Table>
     */
    private function sortTablesByDependencies(
        array $tables,
    ): array {
        if (empty($tables)) {
            return [];
        }

        // Build lookup map, dependency graph, and track original order
        $tableMap = [];
        $dependencies = [];
        $tableNames = [];
        $originalIndex = [];

        foreach ($tables as $index => $table) {
            $tableMap[$table->name] = $table;
            $tableNames[] = $table->name;
            $originalIndex[$table->name] = $index;
            $dependencies[$table->name] = [];

            foreach ($table->foreignKeys as $fk) {
                // Skip self-references (table references itself, e.g., parent_id)
                if ($fk->referencedTable === $table->name) {
                    continue;
                }
                $dependencies[$table->name][] = $fk->referencedTable;
            }
        }

        // Calculate in-degree: count of tables this table depends on (that are in this batch)
        $inDegree = [];
        foreach ($tableNames as $name) {
            $inDegree[$name] = 0;
            foreach ($dependencies[$name] as $dep) {
                if (in_array($dep, $tableNames, true)) {
                    $inDegree[$name]++;
                }
            }
        }

        // Start with tables that have no dependencies
        $queue = [];
        foreach ($inDegree as $name => $degree) {
            if ($degree === 0) {
                $queue[] = $name;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            // Sort queue by original input order for deterministic, stable output
            usort($queue, fn ($a, $b) => $originalIndex[$a] <=> $originalIndex[$b]);
            $current = array_shift($queue);
            $sorted[] = $tableMap[$current];

            // Find tables that depend on current and reduce their in-degree
            foreach ($tableNames as $name) {
                if (in_array($current, $dependencies[$name], true)) {
                    $inDegree[$name]--;
                    if ($inDegree[$name] === 0) {
                        $queue[] = $name;
                    }
                }
            }
        }

        // If we couldn't sort all tables, there's a circular dependency
        // Fall back to original order
        if (count($sorted) !== count($tables)) {
            return $tables;
        }

        return $sorted;
    }

    private function ensureMigrationsDirectoryExists(): void
    {
        $dir = $this->getMigrationsDirectory();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function getMigrationsDirectory(): string
    {
        return $this->basePath . '/' . self::MIGRATIONS_SUBDIR;
    }

    private function generateFilename(
        string $operation,
        string $tableName,
    ): string {
        $timestamp = date('YmdHis', time() + $this->timestampOffset);
        $this->timestampOffset++;

        return "{$timestamp}_{$operation}_$tableName.php";
    }

    /**
     * @param array<string> $upStatements
     * @param array<string> $downStatements
     */
    private function generateMigrationContent(
        array $upStatements,
        array $downStatements,
    ): string {
        $upCode = $this->generateMethodBody($upStatements);
        $downCode = $this->generateMethodBody($downStatements);

        return <<<PHP
<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;

return new class extends Migration {
    public function up(
        ConnectionInterface \$connection,
    ): void {
$upCode
    }

    public function down(
        ConnectionInterface \$connection,
    ): void {
$downCode
    }
};

PHP;
    }

    /**
     * @param array<string> $statements
     */
    private function generateMethodBody(
        array $statements,
    ): string {
        if (empty($statements)) {
            return '        // No SQL statements';
        }

        $lines = [];
        foreach ($statements as $statement) {
            $lines[] = $this->generateExecuteCall($statement);
        }

        return implode("\n\n", $lines);
    }

    private function generateExecuteCall(
        string $sql,
    ): string {
        // Ensure SQL ends with semicolon
        $sql = rtrim($sql);
        if (!str_ends_with($sql, ';')) {
            $sql .= ';';
        }

        // Indent SQL for readability inside nowdoc
        $indentedSql = $this->indentSql($sql);

        return <<<PHP
        \$this->execute(\$connection, <<<'SQL'
$indentedSql
            SQL);
PHP;
    }

    private function indentSql(
        string $sql,
    ): string {
        $lines = explode("\n", $sql);
        $indentedLines = array_map(
            fn (string $line): string => '            ' . $line,
            $lines,
        );

        return implode("\n", $indentedLines);
    }

    private function writeMigration(
        string $filename,
        string $content,
    ): string {
        $path = $this->getMigrationsDirectory() . '/' . $filename;
        file_put_contents($path, $content);

        return $path;
    }
}
