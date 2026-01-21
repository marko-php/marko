<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Command\MigrateCommand;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

/**
 * Helper to capture output.
 *
 * @return array{stream: resource, output: Output}
 */
function createMigrateOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content.
 *
 * @param resource $stream
 */
function getMigrateOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Create a stub Migrator for testing.
 *
 * @param array<string> $pendingMigrations
 * @param array<string> $appliedMigrations
 */
function createMigratorStub(
    array $pendingMigrations = [],
    array $appliedMigrations = [],
    bool $shouldFail = false,
    string $failMessage = 'Migration failed',
): Migrator {
    return new class ($pendingMigrations, $appliedMigrations, $shouldFail, $failMessage) extends Migrator
    {
        /** @var array<string> */
        public array $migrateApplied = [];

        /** @var int */
        public int $migrateCallCount = 0;

        /** @var bool */
        public bool $rollbackCalled = false;

        public function __construct(
            private array $pendingMigrations,
            private array $appliedMigrations,
            private bool $shouldFail,
            private string $failMessage,
        ) {}

        public function migrate(): array
        {
            $this->migrateCallCount++;

            if ($this->shouldFail) {
                throw new MigrationException($this->failMessage);
            }

            $this->migrateApplied = $this->pendingMigrations;

            return $this->pendingMigrations;
        }

        public function rollback(): array
        {
            $this->rollbackCalled = true;

            return $this->migrateApplied;
        }

        public function getPending(): array
        {
            return $this->pendingMigrations;
        }

        public function getApplied(): array
        {
            return $this->appliedMigrations;
        }
    };
}

/**
 * Create a stub EntityDiscovery.
 *
 * @param array<class-string> $entities
 */
function createMigrateEntityDiscovery(
    array $entities = [],
): EntityDiscovery {
    return new class ($entities) extends EntityDiscovery
    {
        public function __construct(
            private array $entities,
        ) {}

        public function discoverInVendor(
            string $vendorPath,
        ): array {
            return $this->entities;
        }

        public function discoverInModules(
            string $modulesPath,
        ): array {
            return [];
        }

        public function discoverInApp(
            string $appPath,
        ): array {
            return [];
        }
    };
}

/**
 * Create a stub IntrospectorInterface.
 *
 * @param array<string, Table> $tables
 */
function createMigrateIntrospector(
    array $tables = [],
): IntrospectorInterface {
    return new class ($tables) implements IntrospectorInterface
    {
        /**
         * @param array<string, Table> $tables
         */
        public function __construct(
            private array $tables,
        ) {}

        public function getTables(): array
        {
            return array_keys($this->tables);
        }

        public function getTable(
            string $name,
        ): ?Table {
            return $this->tables[$name] ?? null;
        }

        public function tableExists(
            string $name,
        ): bool {
            return isset($this->tables[$name]);
        }

        public function getColumns(
            string $table,
        ): array {
            return $this->tables[$table]?->columns ?? [];
        }

        public function getIndexes(
            string $table,
        ): array {
            return $this->tables[$table]?->indexes ?? [];
        }

        public function getForeignKeys(
            string $table,
        ): array {
            return $this->tables[$table]?->foreignKeys ?? [];
        }

        public function getPrimaryKey(
            string $table,
        ): array {
            foreach ($this->getColumns($table) as $column) {
                if ($column->primaryKey) {
                    return [$column->name];
                }
            }

            return [];
        }
    };
}

/**
 * Create a stub DiffCalculator.
 */
function createMigrateDiffCalculator(
    SchemaDiff $diff,
): DiffCalculator {
    return new class ($diff) extends DiffCalculator
    {
        public function __construct(
            private SchemaDiff $diff,
        ) {}

        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return $this->diff;
        }
    };
}

/**
 * Create a stub SqlGeneratorInterface.
 *
 * @param array<string> $upStatements
 */
function createMigrateSqlGenerator(
    array $upStatements = [],
): SqlGeneratorInterface {
    return new class ($upStatements) implements SqlGeneratorInterface
    {
        public function __construct(
            private array $upStatements,
        ) {}

        public function generateUp(
            SchemaDiff $diff,
        ): array {
            return $this->upStatements;
        }

        public function generateDown(
            SchemaDiff $diff,
        ): array {
            return [];
        }

        public function generateCreateTable(
            Table $table,
        ): string {
            return "CREATE TABLE {$table->name}";
        }

        public function generateDropTable(
            string $tableName,
        ): string {
            return "DROP TABLE {$tableName}";
        }

        public function generateAddColumn(
            string $table,
            Column $column,
        ): string {
            return "ALTER TABLE {$table} ADD COLUMN {$column->name}";
        }

        public function generateDropColumn(
            string $table,
            string $columnName,
        ): string {
            return "ALTER TABLE {$table} DROP COLUMN {$columnName}";
        }

        public function generateModifyColumn(
            string $table,
            Column $column,
            Column $oldColumn,
        ): string {
            return "ALTER TABLE {$table} MODIFY COLUMN {$column->name}";
        }

        public function generateAddIndex(
            string $table,
            Index $index,
        ): string {
            return "CREATE INDEX {$index->name} ON {$table}";
        }

        public function generateDropIndex(
            string $table,
            string $indexName,
        ): string {
            return "DROP INDEX {$indexName}";
        }

        public function generateAddForeignKey(
            string $table,
            ForeignKey $foreignKey,
        ): string {
            return "ALTER TABLE {$table} ADD FOREIGN KEY";
        }

        public function generateDropForeignKey(
            string $table,
            string $keyName,
        ): string {
            return "ALTER TABLE {$table} DROP FOREIGN KEY {$keyName}";
        }
    };
}

/**
 * Create a stub MigrationGenerator.
 *
 * @param array<string> $generatedPaths
 */
function createMigrationGeneratorStub(
    array $generatedPaths = [],
): MigrationGenerator {
    return new class ($generatedPaths) extends MigrationGenerator
    {
        /** @var bool */
        public bool $generateCalled = false;

        public function __construct(
            private array $generatedPaths,
        ) {}

        public function generate(
            SchemaDiff $diff,
        ): array {
            $this->generateCalled = true;

            return $this->generatedPaths;
        }
    };
}

it('registers as db:migrate command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(MigrateCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('db:migrate');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(MigrateCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('applies pending migration files', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
    expect($migrator->migrateCallCount)->toBe(1);
    expect($migrator->migrateApplied)->toBe([
        '2024_01_01_000000_create_users_table',
        '2024_01_02_000000_create_posts_table',
    ]);
});

it('generates new migration files from entity diff in development', function (): void {
    // No pending files, but there's a diff
    $migrator = createMigratorStub(pendingMigrations: []);

    $diff = new SchemaDiff(
        tablesToCreate: [
            new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
                indexes: [],
            ),
        ],
    );

    $generator = createMigrationGeneratorStub(
        generatedPaths: ['/app/database/migrations/2024_01_01_000000_create_posts.php'],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: $generator,
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator($diff),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    expect($generator->generateCalled)->toBeTrue();

    $result = getMigrateOutputContent($stream);
    expect($result)->toContain('Generated');
});

it('does not generate migrations in production mode', function (): void {
    // No pending files, but there's a diff
    $migrator = createMigratorStub(pendingMigrations: []);

    $diff = new SchemaDiff(
        tablesToCreate: [
            new Table(
                name: 'posts',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
                indexes: [],
            ),
        ],
    );

    $generator = createMigrationGeneratorStub(
        generatedPaths: ['/app/database/migrations/2024_01_01_000000_create_posts.php'],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: $generator,
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator($diff),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    // Should NOT generate migrations in production
    expect($generator->generateCalled)->toBeFalse();
});

it('shows each migration being applied', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    $result = getMigrateOutputContent($stream);

    expect($result)->toContain('Migrating: 2024_01_01_000000_create_users_table')
        ->and($result)->toContain('Migrating: 2024_01_02_000000_create_posts_table');
});

it('shows SQL statements being executed with --verbose', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_create_users_table'],
    );

    $diff = new SchemaDiff(
        tablesToCreate: [
            new Table(
                name: 'users',
                columns: [
                    new Column(name: 'id', type: 'INT', primaryKey: true),
                ],
                indexes: [],
            ),
        ],
    );

    $sqlGenerator = createMigrateSqlGenerator(
        upStatements: ['CREATE TABLE users (id INT PRIMARY KEY);'],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator($diff),
        sqlGenerator: $sqlGenerator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate', '--verbose']);

    $command->execute($input, $output);

    $result = getMigrateOutputContent($stream);

    expect($result)->toContain('CREATE TABLE');
});

it('groups applied migrations into a batch', function (): void {
    // This test verifies that the migrator groups migrations into a batch
    // The Migrator class handles this internally, so we verify via output
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    // Migrations should be applied together in a single batch
    expect($migrator->migrateCallCount)->toBe(1);
});

it('shows success message with count of applied migrations', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_create_comments_table',
        ],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    $result = getMigrateOutputContent($stream);

    expect($result)->toContain('3 migration(s)');
});

it('shows "Nothing to migrate" when no pending changes', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: [],
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $command->execute($input, $output);

    $result = getMigrateOutputContent($stream);

    expect($result)->toContain('Nothing to migrate');
});

it('rolls back on failure and shows error', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_create_users_table'],
        shouldFail: true,
        failMessage: 'Syntax error in SQL statement',
    );

    $command = new MigrateCommand(
        migrator: $migrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createMigrateOutputStream();
    $input = new Input(['marko', 'db:migrate']);

    $exitCode = $command->execute($input, $output);

    $result = getMigrateOutputContent($stream);

    expect($exitCode)->toBe(1);
    expect($result)->toContain('Error')
        ->and($result)->toContain('Syntax error in SQL statement');
});

it('returns 0 on success, 1 on failure', function (): void {
    // Success case
    $successMigrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_test'],
    );

    $successCommand = new MigrateCommand(
        migrator: $successMigrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream1, 'output' => $output1] = createMigrateOutputStream();
    $input1 = new Input(['marko', 'db:migrate']);

    $exitCode1 = $successCommand->execute($input1, $output1);

    expect($exitCode1)->toBe(0);

    // Failure case
    $failMigrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_test'],
        shouldFail: true,
        failMessage: 'Migration failed',
    );

    $failCommand = new MigrateCommand(
        migrator: $failMigrator,
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: createMigrateEntityDiscovery(),
        introspector: createMigrateIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator(new SchemaDiff()),
        sqlGenerator: createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: false,
    );

    ['stream' => $stream2, 'output' => $output2] = createMigrateOutputStream();
    $input2 = new Input(['marko', 'db:migrate']);

    $exitCode2 = $failCommand->execute($input2, $output2);

    expect($exitCode2)->toBe(1);
});
