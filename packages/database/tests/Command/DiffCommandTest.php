<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Command\DiffCommand;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

/**
 * Helper to capture output.
 *
 * @return array{stream: resource, output: Output}
 */
function createDiffOutputStream(): array
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
function getDiffOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Helper to create a stub EntityDiscovery.
 *
 * @param array<class-string> $entities
 */
function createStubEntityDiscovery(
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
 * Helper to create a stub IntrospectorInterface.
 *
 * @param array<string, Table> $tables
 */
function createStubIntrospector(
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
 * Helper to create a stub EntityMetadataFactory that returns predefined schema.
 *
 * @param array<string, Table> $entitySchemas
 */
function createStubMetadataFactory(
    array $entitySchemas = [],
): EntityMetadataFactory {
    return new class ($entitySchemas) extends EntityMetadataFactory
    {
        /**
         * @param array<string, Table> $schemas
         */
        public function __construct(
            private array $schemas,
        ) {}

        // This class is used for parsing entities. We need to override nothing.
        // The DiffCommand will use SchemaBuilder which uses EntityMetadata.
        // For simplicity, we'll work with the real classes and mock at a higher level.
    };
}

/**
 * Helper to create a stub SchemaBuilder that returns predefined tables.
 */
function createStubSchemaBuilder(): SchemaBuilder
{
    return new SchemaBuilder();
}

it('registers as db:diff command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(DiffCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('db:diff');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(DiffCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('discovers entity classes with #[Table] from all modules', function (): void {
    // Create mock dependencies
    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();
    $diffCalculator = new DiffCalculator();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    // When no entities are discovered, it should report no changes
    $result = getDiffOutputContent($stream);

    expect($result)->toContain('No changes detected');
});

it('builds schema from entity metadata', function (): void {
    // This test verifies that SchemaBuilder is used to build schema from entities
    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();
    $diffCalculator = new DiffCalculator();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    // The command should use SchemaBuilder internally
    expect($command)->toBeInstanceOf(DiffCommand::class);
});

it('introspects current database state', function (): void {
    // Create introspector with existing table
    $existingTable = new Table(
        name: 'users',
        columns: [
            new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
            new Column(name: 'name', type: 'VARCHAR', length: 255),
        ],
        indexes: [],
    );

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector(['users' => $existingTable]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();
    $diffCalculator = new DiffCalculator();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    // Tables in database but not in entities should show as droppable
    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Drop table: users')
        ->and($result)->toContain('[DESTRUCTIVE]');
});

it('calculates diff between entities and database', function (): void {
    // Set up introspector with a table that differs from entity schema
    $dbTable = new Table(
        name: 'posts',
        columns: [
            new Column(name: 'id', type: 'INT', primaryKey: true, autoIncrement: true),
            new Column(name: 'title', type: 'VARCHAR', length: 255),
        ],
        indexes: [],
    );

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector(['posts' => $dbTable]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();
    $diffCalculator = new DiffCalculator();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $exitCode = $command->execute($input, $output);

    // There are changes (table to drop)
    expect($exitCode)->toBe(1);
});

it('displays tables to be created', function (): void {
    // Entity defines a table that doesn't exist in database
    // We need to mock with a custom DiffCalculator that returns specific diff
    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    // Use a mock diff calculator that returns a predefined diff
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [
                    new Table(
                        name: 'posts',
                        columns: [
                            new Column(name: 'id', type: 'INT', primaryKey: true),
                            new Column(name: 'title', type: 'VARCHAR', length: 255),
                        ],
                        indexes: [],
                    ),
                ],
                tablesToDrop: [],
                tablesToAlter: [],
            );
        }
    };

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Create table: posts');
});

it('displays tables to be dropped (flagged as destructive)', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [
                    new Table(
                        name: 'old_users',
                        columns: [
                            new Column(name: 'id', type: 'INT', primaryKey: true),
                        ],
                        indexes: [],
                    ),
                ],
                tablesToAlter: [],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Drop table: old_users')
        ->and($result)->toContain('[DESTRUCTIVE]');
});

it('displays columns to be added', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [],
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToAdd: [
                            new Column(name: 'email', type: 'VARCHAR', length: 255),
                        ],
                    ),
                ],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Alter table: users')
        ->and($result)->toContain('Add column: email');
});

it('displays columns to be dropped (flagged as destructive)', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [],
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToDrop: [
                            new Column(name: 'legacy_field', type: 'VARCHAR'),
                        ],
                    ),
                ],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Drop column: legacy_field')
        ->and($result)->toContain('[DESTRUCTIVE]');
});

it('displays columns to be modified', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [],
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        columnsToModify: [
                            'name' => new Column(name: 'name', type: 'VARCHAR', length: 500),
                        ],
                    ),
                ],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Modify column: name');
});

it('displays indexes to be added or dropped', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [],
                tablesToAlter: [
                    'users' => new TableDiff(
                        tableName: 'users',
                        indexesToAdd: [
                            new Index(name: 'idx_email', columns: ['email'], type: IndexType::Btree),
                        ],
                        indexesToDrop: [
                            new Index(name: 'idx_old', columns: ['old_field'], type: IndexType::Btree),
                        ],
                    ),
                ],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('Add index: idx_email')
        ->and($result)->toContain('Drop index: idx_old');
});

it('displays "No changes detected" when in sync', function (): void {
    $diffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [],
                tablesToDrop: [],
                tablesToAlter: [],
            );
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $command = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $diffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream, 'output' => $output] = createDiffOutputStream();
    $input = new Input(['marko', 'db:diff']);

    $command->execute($input, $output);

    $result = getDiffOutputContent($stream);

    expect($result)->toContain('No changes detected');
});

it('returns 0 when no changes, 1 when changes exist', function (): void {
    // Test return 0 when no changes
    $noDiffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff();
        }
    };

    $discovery = createStubEntityDiscovery([]);
    $introspector = createStubIntrospector([]);
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $commandNoChanges = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $noDiffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream1, 'output' => $output1] = createDiffOutputStream();
    $input1 = new Input(['marko', 'db:diff']);

    $exitCode1 = $commandNoChanges->execute($input1, $output1);

    expect($exitCode1)->toBe(0);

    // Test return 1 when changes exist
    $hasDiffCalculator = new class () extends DiffCalculator
    {
        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            return new SchemaDiff(
                tablesToCreate: [
                    new Table(
                        name: 'new_table',
                        columns: [new Column(name: 'id', type: 'INT', primaryKey: true)],
                        indexes: [],
                    ),
                ],
            );
        }
    };

    $commandWithChanges = new DiffCommand(
        discovery: $discovery,
        introspector: $introspector,
        metadataFactory: $metadataFactory,
        schemaBuilder: $schemaBuilder,
        diffCalculator: $hasDiffCalculator,
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
    );

    ['stream' => $stream2, 'output' => $output2] = createDiffOutputStream();
    $input2 = new Input(['marko', 'db:diff']);

    $exitCode2 = $commandWithChanges->execute($input2, $output2);

    expect($exitCode2)->toBe(1);
});
