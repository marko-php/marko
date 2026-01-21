<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Migration;

use FilesystemIterator;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Migration test helpers.
 */
final class Helpers
{
    /**
     * Recursively removes a directory and all its contents.
     */
    public static function removeDirectory(
        string $dir,
    ): void {
        if (!is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Creates a stub SqlGeneratorInterface for testing.
     *
     * @param list<string> $upStatements SQL statements for generateUp()
     * @param list<string> $downStatements SQL statements for generateDown()
     */
    public static function createSqlGeneratorStub(
        array $upStatements = ['CREATE TABLE "posts" (id INT)'],
        array $downStatements = ['DROP TABLE "posts"'],
    ): SqlGeneratorInterface {
        return new readonly class ($upStatements, $downStatements) implements SqlGeneratorInterface
        {
            public function __construct(
                private array $upStatements,
                private array $downStatements,
            ) {}

            public function generateUp(
                SchemaDiff $diff,
            ): array {
                return $this->upStatements;
            }

            public function generateDown(
                SchemaDiff $diff,
            ): array {
                return $this->downStatements;
            }

            public function generateCreateTable(
                Table $table,
            ): string {
                return '';
            }

            public function generateDropTable(
                string $tableName,
            ): string {
                return '';
            }

            public function generateAddColumn(
                string $table,
                Column $column,
            ): string {
                return '';
            }

            public function generateDropColumn(
                string $table,
                string $columnName,
            ): string {
                return '';
            }

            public function generateModifyColumn(
                string $table,
                Column $column,
                Column $oldColumn,
            ): string {
                return '';
            }

            public function generateAddIndex(
                string $table,
                Index $index,
            ): string {
                return '';
            }

            public function generateDropIndex(
                string $table,
                string $indexName,
            ): string {
                return '';
            }

            public function generateAddForeignKey(
                string $table,
                ForeignKey $foreignKey,
            ): string {
                return '';
            }

            public function generateDropForeignKey(
                string $table,
                string $keyName,
            ): string {
                return '';
            }
        };
    }

    /**
     * Creates a standard posts table SchemaDiff for testing.
     */
    public static function createPostsTableDiff(): SchemaDiff
    {
        $table = new Table('posts', [new Column('id', 'INTEGER', primaryKey: true)]);

        return new SchemaDiff(tablesToCreate: [$table]);
    }

    /**
     * Creates a connection stub that tracks executed SQL and bindings.
     *
     * @param array<string> $executedSql Reference array to track SQL statements
     * @param array<array<mixed>> $executedBindings Reference array to track bindings
     */
    public static function createTrackingConnection(
        array &$executedSql,
        array &$executedBindings,
    ): ConnectionInterface {
        return new class ($executedSql, $executedBindings) implements ConnectionInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$sql,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$bindings,
            ) {}

            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                $this->sql[] = $sql;
                $this->bindings[] = $bindings;

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };
    }

    /**
     * Returns basic empty migration file content for testing.
     */
    public static function getEmptyMigrationContent(): string
    {
        return <<<'PHP'
            <?php
            declare(strict_types=1);
            use Marko\Database\Connection\ConnectionInterface;
            use Marko\Database\Migration\Migration;
            return new class () extends Migration {
                public function up(ConnectionInterface $connection): void {}
                public function down(ConnectionInterface $connection): void {}
            };
            PHP;
    }

    /**
     * Writes standard test migration files (first, second, third) to a directory.
     *
     * @param string $path Directory to write migration files to
     * @param string|null $content Migration content (defaults to empty migration)
     *
     * @return array{first: string, second: string, third: string} File paths written
     */
    public static function writeTestMigrationFiles(
        string $path,
        ?string $content = null,
    ): array {
        $content ??= self::getEmptyMigrationContent();

        $files = [
            'first' => $path . '/2024_01_01_000000_first.php',
            'second' => $path . '/2024_01_02_000000_second.php',
            'third' => $path . '/2024_01_03_000000_third.php',
        ];

        foreach ($files as $file) {
            file_put_contents($file, $content);
        }

        return $files;
    }

    /**
     * Generates migration files using standard posts table setup.
     *
     * @param list<string> $upStatements SQL statements for generateUp()
     * @param list<string> $downStatements SQL statements for generateDown()
     *
     * @return array{paths: list<string>, content: string|null, generator: MigrationGenerator}
     */
    public static function generateTestMigration(
        string $tempDir,
        ?SchemaDiff $diff = null,
        array $upStatements = ['CREATE TABLE "posts" (id INT)'],
        array $downStatements = ['DROP TABLE "posts"'],
    ): array {
        $sqlGenerator = self::createSqlGeneratorStub($upStatements, $downStatements);
        $generator = new MigrationGenerator($sqlGenerator, $tempDir);
        $diff ??= self::createPostsTableDiff();

        $paths = $generator->generate($diff);
        $content = !empty($paths) ? file_get_contents($paths[0]) : null;

        return [
            'paths' => $paths,
            'content' => $content,
            'generator' => $generator,
        ];
    }
}
