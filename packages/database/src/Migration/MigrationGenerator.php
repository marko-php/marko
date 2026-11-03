<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;
use Psr\Clock\ClockInterface;

/**
 * Generates migration PHP files from SchemaDiff objects.
 *
 * Each SQL statement is rendered as a var_export() string literal, so no statement text, including names
 * introspected from the database, can end the literal early and inject PHP into the generated file.
 */
class MigrationGenerator
{
    private const string MIGRATIONS_SUBDIR = 'database/migrations';

    private readonly string $basePath;

    private int $timestampOffset = 0;

    public function __construct(
        private readonly SqlGeneratorInterface $sqlGenerator,
        ProjectPaths $paths,
        private readonly ClockInterface $clock,
    ) {
        $this->basePath = $paths->base;
    }

    /**
     * Generate migration files from a schema diff.
     *
     * @return array<string> Paths to generated migration files
     * @throws MigrationException When a table alteration produces no SQL in either direction
     */
    public function generate(
        SchemaDiff $diff,
    ): array {
        if ($diff->isEmpty()) {
            return [];
        }

        // Every migration is rendered before any file is written, so a refused alteration leaves no partial set
        $migrations = [];

        // Separate migrations for each table creation (in dependency order)
        foreach ($this->sortTablesByDependencies($diff->tablesToCreate) as $table) {
            $migrations[] = ['create', $table->name, new SchemaDiff(tablesToCreate: [$table])];
        }

        // Separate migrations for each table alteration
        foreach ($diff->tablesToAlter as $tableName => $tableDiff) {
            $migrations[] = ['alter', $tableName, new SchemaDiff(tablesToAlter: [$tableName => $tableDiff])];
        }

        // Separate migrations for each table drop (reverse dependency order)
        foreach (array_reverse($this->sortTablesByDependencies($diff->tablesToDrop)) as $table) {
            $migrations[] = ['drop', $table->name, new SchemaDiff(tablesToDrop: [$table])];
        }

        $this->assertSafeIdentifiers($diff);

        $rendered = [];

        foreach ($migrations as [$action, $tableName, $migrationDiff]) {
            $upStatements = $this->sqlGenerator->generateUp($migrationDiff);
            $downStatements = $this->sqlGenerator->generateDown($migrationDiff);

            // An alteration the generator renders as nothing means the diff and the generator disagree; an empty
            // migration would hide that, and the diff would report the same change on every run
            if ($action === 'alter' && $upStatements === [] && $downStatements === []) {
                throw MigrationException::emptyAlterMigration(
                    $tableName,
                    array_map(trim(...), $migrationDiff->tablesToAlter[$tableName]->getSummaryLines()),
                );
            }

            $rendered[] = [$action, $tableName, $this->generateMigrationContent($upStatements, $downStatements)];
        }

        $this->ensureMigrationsDirectoryExists();
        $this->timestampOffset = 0;
        $paths = [];

        foreach ($rendered as [$action, $tableName, $content]) {
            $paths[] = $this->writeMigration($this->generateFilename($action, $tableName), $content);
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
        $timestamp = $this->clock->now()->modify("+$this->timestampOffset seconds")->format('YmdHis');
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

        // var_export() escapes quotes and backslashes, so no statement text can end the string literal early
        return '        $this->execute($connection, ' . var_export($sql, true) . ');';
    }

    /**
     * Refuse table, column, index and constraint names holding control characters. Names come from entities and
     * from database introspection; a newline or NUL in one is never a real schema name, only a way to break out of
     * the SQL or PHP the generator writes.
     *
     * @throws MigrationException When a name in the diff contains a control character
     */
    private function assertSafeIdentifiers(
        SchemaDiff $diff,
    ): void {
        $names = [];

        foreach ([...$diff->tablesToCreate, ...$diff->tablesToDrop] as $table) {
            $names = [
                ...$names,
                $table->name,
                ...$this->columnNames($table->columns),
                ...$this->indexNames($table->indexes),
                ...$this->foreignKeyNames($table->foreignKeys),
            ];
        }

        foreach ($diff->tablesToAlter as $tableName => $tableDiff) {
            $currentTable = $tableDiff->currentTable;
            $names = [
                ...$names,
                (string) $tableName,
                $tableDiff->tableName,
                ...$tableDiff->currentPrimaryKey,
                ...$this->columnNames($tableDiff->columnsToAdd),
                ...$this->columnNames($tableDiff->columnsToDrop),
                ...$this->columnNames($tableDiff->columnsToModify),
                ...$this->columnNames($tableDiff->columnsToModifyFrom),
                ...$this->indexNames($tableDiff->indexesToAdd),
                ...$this->indexNames($tableDiff->indexesToDrop),
                ...$this->foreignKeyNames($tableDiff->foreignKeysToAdd),
                ...$this->foreignKeyNames($tableDiff->foreignKeysToDrop),
                ...($currentTable === null ? [] : [
                    $currentTable->name,
                    ...$this->columnNames($currentTable->columns),
                    ...$this->indexNames($currentTable->indexes),
                    ...$this->foreignKeyNames($currentTable->foreignKeys),
                ]),
            ];
        }

        foreach ($names as $name) {
            if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
                throw MigrationException::controlCharacterInIdentifier($name);
            }
        }
    }

    /**
     * @param array<Column> $columns
     * @return list<string>
     */
    private function columnNames(
        array $columns,
    ): array {
        $names = [];

        foreach ($columns as $column) {
            $names[] = $column->name;

            if ($column->references !== null) {
                $names[] = $column->references;
            }
        }

        return $names;
    }

    /**
     * @param array<Index> $indexes
     * @return list<string>
     */
    private function indexNames(
        array $indexes,
    ): array {
        $names = [];

        foreach ($indexes as $index) {
            $names = [...$names, $index->name, ...$index->columns];
        }

        return $names;
    }

    /**
     * @param array<ForeignKey> $foreignKeys
     * @return list<string>
     */
    private function foreignKeyNames(
        array $foreignKeys,
    ): array {
        $names = [];

        foreach ($foreignKeys as $foreignKey) {
            $names = [
                ...$names,
                $foreignKey->name,
                $foreignKey->referencedTable,
                ...$foreignKey->columns,
                ...$foreignKey->referencedColumns,
            ];
        }

        return $names;
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
