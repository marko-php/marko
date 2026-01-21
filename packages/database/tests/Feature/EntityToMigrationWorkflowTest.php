<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Table as SchemaTable;

// Test entities for the workflow tests

#[Table('workflow_users')]
#[Index(name: 'idx_email', columns: ['email'], unique: true)]
class WorkflowUser extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255)]
    public string $name;

    #[Column(length: 255)]
    public string $email;

    #[Column]
    public bool $isActive = true;
}

#[Table('workflow_posts')]
class WorkflowPost extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255)]
    public string $title;

    #[Column(type: 'TEXT')]
    public string $content;

    /** @noinspection PhpUnused - Accessed via reflection */
    #[Column]
    public int $authorId;
}

// Tests for the complete entity-to-migration workflow

describe('Entity to Migration Workflow', function (): void {
    it('runs complete entity-to-migration workflow', function (): void {
        // Step 1: Parse entity metadata
        $metadataFactory = new EntityMetadataFactory();
        $metadata = $metadataFactory->parse(WorkflowUser::class);

        expect($metadata->tableName)
            ->toBe('workflow_users')
            ->and($metadata->columns)->toHaveCount(4);

        // Step 2: Build schema from metadata
        $schemaBuilder = new SchemaBuilder();
        $table = $schemaBuilder->build($metadata);

        expect($table->name)
            ->toBe('workflow_users')
            ->and($table->columns)->toHaveCount(4);

        // Step 3: Calculate diff against empty database
        $diffCalculator = new DiffCalculator();
        $entitySchema = ['workflow_users' => $table];
        $databaseSchema = []; // Empty database

        $diff = $diffCalculator->calculate($entitySchema, $databaseSchema);

        expect($diff->tablesToCreate)
            ->toHaveCount(1)
            ->and($diff->tablesToCreate[0]->name)->toBe('workflow_users');

        // Step 4: Generate migration SQL using a mock generator
        $sqlGenerator = new class () implements SqlGeneratorInterface
        {
            public function generateUp(
                SchemaDiff $diff,
            ): array {
                $statements = [];
                foreach ($diff->tablesToCreate as $table) {
                    $statements[] = $this->generateCreateTable($table);
                }

                return $statements;
            }

            public function generateDown(
                SchemaDiff $diff,
            ): array {
                $statements = [];
                foreach ($diff->tablesToCreate as $table) {
                    $statements[] = "DROP TABLE $table->name";
                }

                return $statements;
            }

            public function generateCreateTable(
                SchemaTable $table,
            ): string {
                return "CREATE TABLE $table->name";
            }

            public function generateDropTable(
                string $tableName,
            ): string {
                return "DROP TABLE $tableName";
            }

            public function generateAddColumn(
                string $table,
                SchemaColumn $column,
            ): string {
                return "ALTER TABLE $table ADD COLUMN $column->name";
            }

            public function generateDropColumn(
                string $table,
                string $columnName,
            ): string {
                return "ALTER TABLE $table DROP COLUMN $columnName";
            }

            public function generateModifyColumn(
                string $table,
                SchemaColumn $column,
                SchemaColumn $oldColumn,
            ): string {
                return "ALTER TABLE $table MODIFY COLUMN $column->name";
            }

            public function generateAddIndex(
                string $table,
                \Marko\Database\Schema\Index $index,
            ): string {
                return "CREATE INDEX $index->name ON $table";
            }

            public function generateDropIndex(
                string $table,
                string $indexName,
            ): string {
                return "DROP INDEX $indexName";
            }

            public function generateAddForeignKey(
                string $table,
                ForeignKey $foreignKey,
            ): string {
                return "ALTER TABLE $table ADD FOREIGN KEY";
            }

            public function generateDropForeignKey(
                string $table,
                string $keyName,
            ): string {
                return "ALTER TABLE $table DROP FOREIGN KEY $keyName";
            }
        };

        $upStatements = $sqlGenerator->generateUp($diff);

        expect($upStatements)
            ->toHaveCount(1)
            ->and($upStatements[0])
            ->toContain('CREATE TABLE')
            ->toContain('workflow_users');

        // Step 5: Generate migration file
        $tempDir = sys_get_temp_dir() . '/marko_workflow_test_' . uniqid();
        mkdir($tempDir);

        $projectPaths = new ProjectPaths($tempDir);
        $generator = new MigrationGenerator($sqlGenerator, $projectPaths);
        $paths = $generator->generate($diff);

        expect($paths)
            ->toHaveCount(1)
            ->and(file_exists($paths[0]))->toBeTrue();

        // Verify migration file content
        $content = file_get_contents($paths[0]);
        expect($content)
            ->toContain('extends Migration')
            ->toContain('function up')
            ->toContain('function down');

        // Cleanup
        array_map('unlink', glob($tempDir . '/database/migrations/*.php'));
        rmdir($tempDir . '/database/migrations');
        rmdir($tempDir . '/database');
        rmdir($tempDir);
    });

    it('creates tables from entity definitions', function (): void {
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();

        // Parse and build schemas for multiple entities
        $userMetadata = $metadataFactory->parse(WorkflowUser::class);
        $postMetadata = $metadataFactory->parse(WorkflowPost::class);

        $userTable = $schemaBuilder->build($userMetadata);
        $postTable = $schemaBuilder->build($postMetadata);

        // Verify table structures
        expect($userTable->name)
            ->toBe('workflow_users')
            ->and($userTable->columns)->toHaveCount(4)
            ->and(array_map(fn ($col) => $col->name, $userTable->columns))
            ->toContain('id')
            ->toContain('name')
            ->toContain('email')
            ->toContain('isActive')
            ->and($postTable->name)->toBe('workflow_posts')
            ->and($postTable->columns)->toHaveCount(4)
            ->and(array_map(fn ($col) => $col->name, $postTable->columns))
            ->toContain('id')
            ->toContain('title')
            ->toContain('content')
            ->toContain('authorId');

        // Verify primary key detection
        $idColumn = array_filter($userTable->columns, fn ($col) => $col->name === 'id');
        $idColumn = reset($idColumn);
        expect($idColumn->primaryKey)
            ->toBeTrue()
            ->and($idColumn->autoIncrement)->toBeTrue();
    });

    it('detects and generates migrations for entity changes', function (): void {
        $diffCalculator = new DiffCalculator();

        // Current database state (existing table)
        $existingTable = new SchemaTable(
            name: 'workflow_users',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new SchemaColumn(name: 'name', type: 'VARCHAR', length: 255),
            ],
            indexes: [],
        );

        // Entity-defined schema (has new columns)
        $newTable = new SchemaTable(
            name: 'workflow_users',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
                new SchemaColumn(name: 'name', type: 'VARCHAR', length: 255),
                new SchemaColumn(name: 'email', type: 'VARCHAR', length: 255),
                new SchemaColumn(name: 'isActive', type: 'BOOLEAN'),
            ],
            indexes: [],
        );

        $diff = $diffCalculator->calculate(
            ['workflow_users' => $newTable],
            ['workflow_users' => $existingTable],
        );

        // Should detect columns to add
        expect($diff->tablesToCreate)
            ->toBeEmpty()
            ->and($diff->tablesToDrop)->toBeEmpty()
            ->and($diff->tablesToAlter)->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['workflow_users'];
        expect($tableDiff->columnsToAdd)->toHaveCount(2);

        $addedColumnNames = array_map(fn ($col) => $col->name, $tableDiff->columnsToAdd);
        expect($addedColumnNames)
            ->toContain('email')
            ->toContain('isActive');
    });

    it('detects dropped columns in entity changes', function (): void {
        $diffCalculator = new DiffCalculator();

        // Current database state (has extra column)
        $existingTable = new SchemaTable(
            name: 'workflow_users',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true),
                new SchemaColumn(name: 'name', type: 'VARCHAR', length: 255),
                new SchemaColumn(name: 'deprecated_field', type: 'VARCHAR', length: 255),
            ],
            indexes: [],
        );

        // Entity-defined schema (removed deprecated_field)
        $newTable = new SchemaTable(
            name: 'workflow_users',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true),
                new SchemaColumn(name: 'name', type: 'VARCHAR', length: 255),
            ],
            indexes: [],
        );

        $diff = $diffCalculator->calculate(
            ['workflow_users' => $newTable],
            ['workflow_users' => $existingTable],
        );

        expect($diff->tablesToAlter)
            ->toHaveCount(1);

        $tableDiff = $diff->tablesToAlter['workflow_users'];
        expect($tableDiff->columnsToDrop)
            ->toHaveCount(1)
            ->and($tableDiff->columnsToDrop[0]->name)->toBe('deprecated_field');
    });

    it('detects new tables to create', function (): void {
        $diffCalculator = new DiffCalculator();

        $newTable = new SchemaTable(
            name: 'new_table',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true),
            ],
            indexes: [],
        );

        $diff = $diffCalculator->calculate(
            ['new_table' => $newTable],
            [], // Empty database
        );

        expect($diff->tablesToCreate)
            ->toHaveCount(1)
            ->and($diff->tablesToCreate[0]->name)->toBe('new_table');
    });

    it('detects tables to drop', function (): void {
        $diffCalculator = new DiffCalculator();

        $existingTable = new SchemaTable(
            name: 'old_table',
            columns: [
                new SchemaColumn(name: 'id', type: 'INT', primaryKey: true),
            ],
            indexes: [],
        );

        $diff = $diffCalculator->calculate(
            [], // No entities
            ['old_table' => $existingTable],
        );

        expect($diff->tablesToDrop)
            ->toHaveCount(1)
            ->and($diff->tablesToDrop[0]->name)->toBe('old_table');
    });
});
