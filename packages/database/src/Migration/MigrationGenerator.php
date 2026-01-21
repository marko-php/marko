<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

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
    private const MIGRATIONS_SUBDIR = 'database/migrations';

    public function __construct(
        private SqlGeneratorInterface $sqlGenerator,
        private string $basePath,
    ) {}

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

        $paths = [];

        // Generate separate migrations for each table creation
        foreach ($diff->tablesToCreate as $table) {
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

        // Generate separate migrations for each table drop
        foreach ($diff->tablesToDrop as $table) {
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
        $timestamp = date('YmdHis');

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
