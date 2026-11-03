<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\ErrorOutput;
use Marko\Core\Command\Input;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Command\MigrateCommand;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table;
use Marko\Database\Tests\Command\Fixtures\ExpiringTokenEntity;
use Marko\Database\Tests\Command\Helpers;
use Marko\Database\Tests\Diff\Fixtures\CountingMatcherIntrospector;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\BasicExtenderEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderParentEntity;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * Create a stub Migrator for testing.
 *
 * @param array<string> $pendingMigrations
 * @param array<string> $appliedMigrations
 *
 * @return Migrator&object{migrateApplied: array<string>, migrateCallCount: int, rollbackCalled: bool}
 */
function createMigratorStub(
    array $pendingMigrations = [],
    array $appliedMigrations = [],
    bool $shouldFail = false,
    string $failMessage = 'Migration failed',
): Migrator {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($pendingMigrations, $appliedMigrations, $shouldFail, $failMessage) extends Migrator
    {
        /** @var array<string> */
        public array $migrateApplied = [];

        public int $migrateCallCount = 0;

        public bool $rollbackCalled = false;

        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly array $pendingMigrations,
            private readonly array $appliedMigrations,
            private readonly bool $shouldFail,
            private readonly string $failMessage,
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
 * Create a stub DiffCalculator.
 */
function createMigrateDiffCalculator(
    SchemaDiff $diff,
): DiffCalculator {
    return new class ($diff) extends DiffCalculator
    {
        public function __construct(
            private readonly SchemaDiff $diff,
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
    return new readonly class ($upStatements) implements SqlGeneratorInterface
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
            return "CREATE TABLE $table->name";
        }

        public function generateDropTable(
            string $tableName,
        ): string {
            return "DROP TABLE $tableName";
        }

        public function generateAddColumn(
            string $table,
            Column $column,
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
            Column $column,
            Column $oldColumn,
        ): string {
            return "ALTER TABLE $table MODIFY COLUMN $column->name";
        }

        public function generateAddIndex(
            string $table,
            Index $index,
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
}

/**
 * Create a stub MigrationGenerator.
 *
 * @param array<string> $generatedPaths
 *
 * @return MigrationGenerator&object{generateCalled: bool}
 */
function createMigrationGeneratorStub(
    array $generatedPaths = [],
): MigrationGenerator {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($generatedPaths) extends MigrationGenerator
    {
        public bool $generateCalled = false;

        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly array $generatedPaths,
        ) {}

        public function generate(
            SchemaDiff $diff,
        ): array {
            $this->generateCalled = true;

            return $this->generatedPaths;
        }
    };
}

/**
 * Create a stub DataMigrator for testing.
 *
 * @param array<array{name: string, path: string, source: string}> $pendingMigrations
 * @param array<string> $appliedMigrations
 *
 * @return DataMigrator&object{migrateApplied: array<string>, migrateCallCount: int}
 */
function createDataMigratorStub(
    array $pendingMigrations = [],
    array $appliedMigrations = [],
    bool $shouldFail = false,
    string $failMessage = 'Data migration failed',
): DataMigrator {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($pendingMigrations, $appliedMigrations, $shouldFail, $failMessage) extends DataMigrator
    {
        /** @var array<string> */
        public array $migrateApplied = [];

        public int $migrateCallCount = 0;

        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly array $pendingMigrations,
            private readonly array $appliedMigrations,
            private readonly bool $shouldFail,
            private readonly string $failMessage,
        ) {}

        public function migrate(): array
        {
            $this->migrateCallCount++;

            if ($this->shouldFail) {
                throw new MigrationException($this->failMessage);
            }

            $names = array_column($this->pendingMigrations, 'name');
            $this->migrateApplied = $names;

            return $names;
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
 * Helper to create a MigrateCommand with standard dependencies.
 *
 * @param Migrator&object{migrateApplied: array<string>, migrateCallCount: int, rollbackCalled: bool}|null $migrator
 * @param DataMigrator&object{migrateApplied: array<string>, migrateCallCount: int}|null $dataMigrator
 * @param MigrationGenerator&object{generateCalled: bool}|null $generator
 */
function createMigrateCommand(
    ?Migrator $migrator = null,
    ?DataMigrator $dataMigrator = null,
    ?MigrationGenerator $generator = null,
    ?SchemaDiff $diff = null,
    ?SqlGeneratorInterface $sqlGenerator = null,
    ?string $appEnv = 'local',
    ?ConfirmationPrompterInterface $prompter = null,
): MigrateCommand {
    return new MigrateCommand(
        migrator: $migrator ?? createMigratorStub(),
        dataMigrator: $dataMigrator ?? createDataMigratorStub(),
        migrationGenerator: $generator ?? createMigrationGeneratorStub(),
        entityDiscovery: Helpers::createStubEntityDiscovery(),
        introspector: Helpers::createStubIntrospector(),
        schemaRegistry: new SchemaRegistry(new EntityMetadataFactory(), new SchemaBuilder()),
        diffCalculator: createMigrateDiffCalculator($diff ?? new SchemaDiff()),
        sqlGenerator: $sqlGenerator ?? createMigrateSqlGenerator(),
        paths: new ProjectPaths('/test'),
        appEnvironment: new AppEnvironment($appEnv === null ? [] : ['APP_ENV' => $appEnv]),
        confirmationPrompter: $prompter ?? new FakeConfirmationPrompter(interactive: false),
        expressionDefaultCanonicalizer: new ExpressionDefaultCanonicalizer(Helpers::createStubIntrospector()),
        errorOutput: new ErrorOutput(fopen('php://memory', 'r+')),
    );
}

/**
 * Helper to execute a MigrateCommand and return the output.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeMigrateCommand(
    MigrateCommand $command,
    array $args = ['marko', 'db:migrate'],
): array {
    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = Helpers::getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as db:migrate command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(MigrateCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:migrate');
});

it('declares generate, no-generate, force, verbose and v as value-less flags', function (): void {
    $attribute = new ReflectionClass(MigrateCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($attribute->flags)->toBe(['generate', 'no-generate', 'force', 'verbose', 'v']);
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(MigrateCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('applies pending migration files', function (): void {
    /** @var Migrator&object{migrateApplied: array<string>, migrateCallCount: int, rollbackCalled: bool} $migrator */
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
    );

    $command = createMigrateCommand(migrator: $migrator);

    ['exitCode' => $exitCode] = executeMigrateCommand($command);

    expect($exitCode)->toBe(0)
        ->and($migrator->migrateCallCount)->toBe(1)
        ->and($migrator->migrateApplied)->toBe([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ]);
});

it('generates new migration files from entity diff in development', function (): void {
    $migrator = createMigratorStub();

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

    /** @var MigrationGenerator&object{generateCalled: bool} $generator */
    $generator = createMigrationGeneratorStub(
        generatedPaths: ['/app/database/migrations/2024_01_01_000000_create_posts.php'],
    );

    $command = createMigrateCommand(
        migrator: $migrator,
        generator: $generator,
        diff: $diff,
    );

    ['output' => $output] = executeMigrateCommand($command);

    expect($generator->generateCalled)->toBeTrue()
        ->and($output)->toContain('Generated');
});

it('shows SQL statements when generating migrations with --verbose', function (): void {
    $migrator = createMigratorStub();

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

    $sqlGenerator = createMigrateSqlGenerator(
        upStatements: ['CREATE TABLE posts (id INT PRIMARY KEY);'],
    );

    $command = createMigrateCommand(
        migrator: $migrator,
        generator: $generator,
        diff: $diff,
        sqlGenerator: $sqlGenerator,
    );

    ['output' => $output] = executeMigrateCommand($command, ['marko', 'db:migrate', '--verbose']);

    expect($output)->toContain('Generated')
        ->and($output)->toContain('SQL statements:')
        ->and($output)->toContain('CREATE TABLE posts');
});

it('does not generate migrations in production mode', function (): void {
    $migrator = createMigratorStub();

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

    /** @var MigrationGenerator&object{generateCalled: bool} $generator */
    $generator = createMigrationGeneratorStub(
        generatedPaths: ['/app/database/migrations/2024_01_01_000000_create_posts.php'],
    );

    $command = createMigrateCommand(
        migrator: $migrator,
        generator: $generator,
        diff: $diff,
        appEnv: 'production',
    );

    executeMigrateCommand($command);

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

    $command = createMigrateCommand(migrator: $migrator);

    ['output' => $output] = executeMigrateCommand($command);

    expect($output)->toContain('Migrating: 2024_01_01_000000_create_users_table')
        ->and($output)->toContain('Migrating: 2024_01_02_000000_create_posts_table');
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

    $command = createMigrateCommand(
        migrator: $migrator,
        diff: $diff,
        sqlGenerator: $sqlGenerator,
    );

    ['output' => $output] = executeMigrateCommand($command, ['marko', 'db:migrate', '--verbose']);

    expect($output)->toContain('CREATE TABLE');
});

it('groups applied migrations into a batch', function (): void {
    /** @var Migrator&object{migrateApplied: array<string>, migrateCallCount: int, rollbackCalled: bool} $migrator */
    $migrator = createMigratorStub(
        pendingMigrations: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
    );

    $command = createMigrateCommand(migrator: $migrator);

    executeMigrateCommand($command);

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

    $command = createMigrateCommand(migrator: $migrator);

    ['output' => $output] = executeMigrateCommand($command);

    expect($output)->toContain('3 schema migration(s)');
});

it('shows "Nothing to migrate" when no pending changes', function (): void {
    $migrator = createMigratorStub();

    $command = createMigrateCommand(migrator: $migrator);

    ['output' => $output] = executeMigrateCommand($command);

    expect($output)->toContain('Nothing to migrate');
});

it('rolls back on failure and shows error', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_create_users_table'],
        shouldFail: true,
        failMessage: 'Syntax error in SQL statement',
    );

    $command = createMigrateCommand(migrator: $migrator);

    ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Error')
        ->and($output)->toContain('Syntax error in SQL statement');
});

it('returns 0 on success, 1 on failure', function (): void {
    // Success case
    $successMigrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_test'],
    );

    $successCommand = createMigrateCommand(migrator: $successMigrator);

    ['exitCode' => $exitCode1] = executeMigrateCommand($successCommand);

    expect($exitCode1)->toBe(0);

    // Failure case
    $failMigrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_test'],
        shouldFail: true,
    );

    $failCommand = createMigrateCommand(migrator: $failMigrator);

    ['exitCode' => $exitCode2] = executeMigrateCommand($failCommand);

    expect($exitCode2)->toBe(1);
});

it('applies pending data migrations', function (): void {
    /** @var DataMigrator&object{migrateApplied: array<string>, migrateCallCount: int} $dataMigrator */
    $dataMigrator = createDataMigratorStub(
        pendingMigrations: [
            ['name' => '001_seed_countries', 'path' => '/app/Data/001_seed_countries.php', 'source' => 'app/core'],
            ['name' => '002_seed_currencies', 'path' => '/app/Data/002_seed_currencies.php', 'source' => 'app/core'],
        ],
    );

    $command = createMigrateCommand(dataMigrator: $dataMigrator);

    ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

    expect($exitCode)->toBe(0)
        ->and($dataMigrator->migrateCallCount)->toBe(1)
        ->and($dataMigrator->migrateApplied)->toBe(['001_seed_countries', '002_seed_currencies'])
        ->and($output)->toContain('Data migrating: 001_seed_countries')
        ->and($output)->toContain('Data migrating: 002_seed_currencies')
        ->and($output)->toContain('2 data migration(s)');
});

it('applies both schema and data migrations', function (): void {
    $migrator = createMigratorStub(
        pendingMigrations: ['2024_01_01_000000_create_users_table'],
    );

    $dataMigrator = createDataMigratorStub(
        pendingMigrations: [
            ['name' => '001_seed_countries', 'path' => '/app/Data/001_seed_countries.php', 'source' => 'app/core'],
        ],
    );

    $command = createMigrateCommand(migrator: $migrator, dataMigrator: $dataMigrator);

    ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('1 schema migration(s)')
        ->and($output)->toContain('1 data migration(s)')
        ->and($output)->toContain('Migration complete');
});

it('handles data migration failure', function (): void {
    $dataMigrator = createDataMigratorStub(
        pendingMigrations: [
            ['name' => '001_seed_countries', 'path' => '/app/Data/001_seed_countries.php', 'source' => 'app/core'],
        ],
        shouldFail: true,
        failMessage: 'Failed to insert country data',
    );

    $command = createMigrateCommand(dataMigrator: $dataMigrator);

    ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Error')
        ->and($output)->toContain('Failed to insert country data');
});

it('excludes migrations table from diff calculation', function (): void {
    // Create introspector that returns migrations table (simulating it exists in DB)
    $migrationsTable = new Table(
        name: 'migrations',
        columns: [
            new Column(name: 'name', type: 'VARCHAR', length: 255),
            new Column(name: 'batch', type: 'INT'),
        ],
        indexes: [],
    );

    $introspector = Helpers::createStubIntrospector(['migrations' => $migrationsTable]);

    /** @var MigrationGenerator&object{generateCalled: bool} $generator */
    $generator = createMigrationGeneratorStub();

    $command = new MigrateCommand(
        migrator: createMigratorStub(),
        dataMigrator: createDataMigratorStub(),
        migrationGenerator: $generator,
        entityDiscovery: Helpers::createStubEntityDiscovery(),
        introspector: $introspector,
        schemaRegistry: new SchemaRegistry(new EntityMetadataFactory(), new SchemaBuilder()),
        diffCalculator: new DiffCalculator(),
        sqlGenerator: createMigrateSqlGenerator(),
        paths: new ProjectPaths('/test'),
        appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
        confirmationPrompter: new FakeConfirmationPrompter(interactive: false),
        expressionDefaultCanonicalizer: new ExpressionDefaultCanonicalizer($introspector),
        errorOutput: new ErrorOutput(fopen('php://memory', 'r+')),
    );

    ['output' => $output] = executeMigrateCommand($command);

    // Should NOT generate a migration to drop migrations table
    expect($output)->toContain('Nothing to migrate')
        ->and($generator->generateCalled)->toBeFalse();
});

it('merges extender columns into parent table schema before computing diff (regression for #66)', function (): void {
    // Capture the entitySchema passed to DiffCalculator so we can prove the
    // extender's `extra` column was merged into the parent `users` table
    // before the diff was computed. Pre-fix the command bypassed
    // SchemaRegistry's extender merge and the extender column would be
    // absent from $entitySchema['users'].
    /** @var array<string, Table>|null $captured */
    $captured = null;

    $capturingCalculator = new class ($captured) extends DiffCalculator
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property captures schema for assertion */
            private ?array &$captured,
        ) {}

        public function calculate(
            array $entitySchema,
            array $databaseSchema,
        ): SchemaDiff {
            $this->captured = $entitySchema;

            return new SchemaDiff();
        }
    };

    $command = new MigrateCommand(
        migrator: createMigratorStub(),
        dataMigrator: createDataMigratorStub(),
        migrationGenerator: createMigrationGeneratorStub(),
        entityDiscovery: Helpers::createStubEntityDiscovery([ExtenderParentEntity::class, BasicExtenderEntity::class]),
        introspector: Helpers::createStubIntrospector(),
        schemaRegistry: new SchemaRegistry(new EntityMetadataFactory(), new SchemaBuilder()),
        diffCalculator: $capturingCalculator,
        sqlGenerator: createMigrateSqlGenerator(),
        paths: new ProjectPaths('/test'),
        appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
        confirmationPrompter: new FakeConfirmationPrompter(interactive: false),
        expressionDefaultCanonicalizer: new ExpressionDefaultCanonicalizer(Helpers::createStubIntrospector()),
        errorOutput: new ErrorOutput(fopen('php://memory', 'r+')),
    );

    executeMigrateCommand($command);

    expect($captured)->toHaveKey('users')
        ->and($captured['users']->columns)->toHaveCount(2);

    $columnNames = array_map(fn (Column $c): string => $c->name, $captured['users']->columns);

    expect($columnNames)->toContain('id')
        ->and($columnNames)->toContain('extra');
});

/**
 * An introspector whose tokens table stores the ExpiringTokenEntity expression default in PostgreSQL's spelling,
 * and says the database would store the entity's expression the same way.
 */
function createRespelledDefaultIntrospector(
    ?Closure $matches = null,
): CountingMatcherIntrospector {
    return new CountingMatcherIntrospector(
        $matches ?? fn (): bool => true,
        [
            'tokens' => new Table(name: 'tokens', columns: [
                new Column(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new Column(
                    name: 'expires_at',
                    type: 'timestamp',
                    default: new Expression("(now() + '1 day'::interval)"),
                ),
            ]),
        ],
    );
}

/**
 * A MigrateCommand that diffs ExpiringTokenEntity against the introspector with the real DiffCalculator.
 */
function createExpressionDefaultMigrateCommand(
    CountingMatcherIntrospector $introspector,
    MigrationGenerator $generator,
    string $appEnv,
    ?Migrator $migrator = null,
    ?ErrorOutput $errorOutput = null,
): MigrateCommand {
    return new MigrateCommand(
        migrator: $migrator ?? createMigratorStub(),
        dataMigrator: createDataMigratorStub(),
        migrationGenerator: $generator,
        entityDiscovery: Helpers::createStubEntityDiscovery([ExpiringTokenEntity::class]),
        introspector: $introspector,
        schemaRegistry: new SchemaRegistry(new EntityMetadataFactory(), new SchemaBuilder()),
        diffCalculator: new DiffCalculator(),
        sqlGenerator: createMigrateSqlGenerator(),
        paths: new ProjectPaths('/test'),
        appEnvironment: new AppEnvironment(['APP_ENV' => $appEnv]),
        confirmationPrompter: new FakeConfirmationPrompter(interactive: false),
        expressionDefaultCanonicalizer: new ExpressionDefaultCanonicalizer($introspector),
        errorOutput: $errorOutput ?? new ErrorOutput(fopen('php://memory', 'r+')),
    );
}

it(
    'generates no migration when the database stores the entity expression default in its own spelling',
    function (): void {
        $introspector = createRespelledDefaultIntrospector();

        /** @var MigrationGenerator&object{generateCalled: bool} $generator */
        $generator = createMigrationGeneratorStub();

        ['output' => $output] = executeMigrateCommand(
            createExpressionDefaultMigrateCommand($introspector, $generator, 'local'),
        );

        expect($output)->toContain('Nothing to migrate')
            ->and($generator->generateCalled)->toBeFalse()
            ->and($introspector->probes)->toHaveCount(1);
    },
);

it('reports no drift when the database stores the entity expression default in its own spelling', function (): void {
    $introspector = createRespelledDefaultIntrospector();

    ['output' => $output] = executeMigrateCommand(
        createExpressionDefaultMigrateCommand($introspector, createMigrationGeneratorStub(), 'production'),
    );

    expect($output)->not->toContain('Warning: Entity schema differs from database.')
        ->and($introspector->probes)->toHaveCount(1);
});

/**
 * An introspector whose probe fails the way a deploy user without the temporary table privilege makes it fail.
 */
function createFailingProbeIntrospector(): CountingMatcherIntrospector
{
    return createRespelledDefaultIntrospector(
        function (string $table, string $column, Expression $expression): bool {
            throw ExpressionDefaultProbeException::rejected(
                $table,
                $column,
                $expression->sql,
                'permission denied to create temporary tables in database "app"',
            );
        },
    );
}

describe('a failing expression default probe', function (): void {
    it(
        'warns on stderr and exits with the migration status when the post-migration drift check fails',
        function (): void {
            $stderr = fopen('php://memory', 'r+');
            $migrator = createMigratorStub(pendingMigrations: ['2026_10_06_000000_create_tokens']);

            ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand(
                createExpressionDefaultMigrateCommand(
                    createFailingProbeIntrospector(),
                    createMigrationGeneratorStub(),
                    'production',
                    $migrator,
                    new ErrorOutput($stderr),
                ),
            );
            $warning = Helpers::getOutputContent($stderr);

            expect($exitCode)->toBe(0)
                ->and($migrator->migrateCallCount)->toBe(1)
                ->and($output)->toContain('Migration complete.')
                ->and($output)->not->toContain('Error:')
                ->and($warning)->toContain('Warning: The drift check could not compare')
                ->and($warning)->toContain("column 'tokens.expires_at'")
                ->and($warning)->toContain('permission denied to create temporary tables')
                ->and($warning)->toContain('CREATE TEMPORARY TABLES')
                ->and($warning)->toContain('The migrations were applied');
        },
    );

    it('still fails db:migrate in development, where it generates migrations', function (): void {
        $stderr = fopen('php://memory', 'r+');

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand(
            createExpressionDefaultMigrateCommand(
                createFailingProbeIntrospector(),
                createMigrationGeneratorStub(),
                'local',
                errorOutput: new ErrorOutput($stderr),
            ),
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain(
                "Error: The database rejected the default expression \"now() + interval '1 day'\"",
            );
    });
});

function createTableDiffForMigrate(): SchemaDiff
{
    return new SchemaDiff(
        tablesToCreate: [
            new Table(name: 'posts', columns: [new Column(name: 'id', type: 'INT', primaryKey: true)]),
        ],
    );
}

function createDestructiveDiffForMigrate(): SchemaDiff
{
    return new SchemaDiff(
        tablesToAlter: [
            'shows' => new TableDiff(
                tableName: 'shows',
                columnsToDrop: [new Column(name: 'legacy', type: 'TEXT')],
                indexesToDrop: [new Index(name: 'shows_old_idx', columns: ['legacy'])],
            ),
        ],
    );
}

function createDestructiveSqlGeneratorForMigrate(): SqlGeneratorInterface
{
    return createMigrateSqlGenerator(
        upStatements: ['ALTER TABLE "shows" DROP COLUMN "legacy"', 'DROP INDEX "shows_old_idx"'],
    );
}

describe('environment gating', function (): void {
    it('applies pending migrations but does not generate in production', function (): void {
        $migrator = createMigratorStub(pendingMigrations: ['2024_01_01_000000_create_users_table']);
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(
            migrator: $migrator,
            generator: $generator,
            diff: createTableDiffForMigrate(),
            appEnv: 'production',
        );

        ['exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(0)
            ->and($migrator->migrateApplied)->toBe(['2024_01_01_000000_create_users_table'])
            ->and($generator->generateCalled)->toBeFalse();
    });

    it('does not generate when APP_ENV is unset', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(generator: $generator, diff: createTableDiffForMigrate(), appEnv: null);

        executeMigrateCommand($command);

        expect($generator->generateCalled)->toBeFalse();
    });

    it('lists the differing statements in the production drift warning', function (): void {
        $command = createMigrateCommand(
            diff: createTableDiffForMigrate(),
            sqlGenerator: createMigrateSqlGenerator(upStatements: ['CREATE TABLE "posts" ("id" INTEGER)']),
            appEnv: 'production',
        );

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Warning: Entity schema differs from database.')
            ->and($output)->toContain('  CREATE TABLE "posts" ("id" INTEGER)')
            ->and($output)->toContain('Run db:migrate in development to generate a migration');
    });

    it('prints the drift warning in production even after applying pending migrations', function (): void {
        $command = createMigrateCommand(
            migrator: createMigratorStub(pendingMigrations: ['2024_01_01_000000_create_users_table']),
            diff: createTableDiffForMigrate(),
            sqlGenerator: createMigrateSqlGenerator(upStatements: ['CREATE TABLE "posts" ("id" INTEGER)']),
            appEnv: 'production',
        );

        ['output' => $output] = executeMigrateCommand($command);

        expect($output)->toContain('Applied 1 schema migration(s).')
            ->and($output)->toContain('Warning: Entity schema differs from database.');
    });

    it('does not print a drift warning in production when the schema matches', function (): void {
        $command = createMigrateCommand(appEnv: 'production');

        ['output' => $output] = executeMigrateCommand($command);

        expect($output)->not->toContain('Warning')
            ->and($output)->toContain('Nothing to migrate.');
    });

    it('does not generate when APP_ENV is staging', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(generator: $generator, diff: createTableDiffForMigrate(), appEnv: 'staging');

        ['output' => $output] = executeMigrateCommand($command);

        expect($generator->generateCalled)->toBeFalse()
            ->and($output)->toContain('Warning: Entity schema differs from database.');
    });

    it('generates in local', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(generator: $generator, diff: createTableDiffForMigrate(), appEnv: 'local');

        executeMigrateCommand($command);

        expect($generator->generateCalled)->toBeTrue();
    });

    it('does not generate in local with --no-generate', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(generator: $generator, diff: createTableDiffForMigrate(), appEnv: 'local');

        ['output' => $output] = executeMigrateCommand($command, ['marko', 'db:migrate', '--no-generate']);

        expect($generator->generateCalled)->toBeFalse()
            ->and($output)->not->toContain('Warning');
    });

    it('generates outside development with --generate', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createTableDiffForMigrate(),
            appEnv: 'production',
        );

        ['output' => $output] = executeMigrateCommand($command, ['marko', 'db:migrate', '--generate']);

        expect($generator->generateCalled)->toBeTrue()
            ->and($output)->not->toContain('Warning');
    });

    it('rejects --generate combined with --no-generate', function (): void {
        $migrator = createMigratorStub(pendingMigrations: ['2024_01_01_000000_create_users_table']);
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);

        $command = createMigrateCommand(migrator: $migrator, generator: $generator, diff: createTableDiffForMigrate());

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand(
            $command,
            ['marko', 'db:migrate', '--generate', '--no-generate'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('--generate and --no-generate cannot be used together')
            ->and($migrator->migrateCallCount)->toBe(0)
            ->and($generator->generateCalled)->toBeFalse();
    });
});

describe('destructive changes', function (): void {
    it('lists each destructive statement before generating', function (): void {
        $command = createMigrateCommand(
            generator: createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']),
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
        );

        ['output' => $output] = executeMigrateCommand($command, ['marko', 'db:migrate', '--force']);

        expect($output)->toContain('This migration would remove existing database objects:')
            ->and($output)->toContain('  ALTER TABLE "shows" DROP COLUMN "legacy"')
            ->and($output)->toContain('  DROP INDEX "shows_old_idx"');
    });

    it('refuses to generate destructive changes non-interactively without --force', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);
        $prompter = new FakeConfirmationPrompter(interactive: false);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
            prompter: $prompter,
        );

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Re-run with --force to generate these changes.')
            ->and($prompter->asked)->toBeEmpty()
            ->and($generator->generateCalled)->toBeFalse();
    });

    it('generates destructive changes non-interactively with --force', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);
        $prompter = new FakeConfirmationPrompter(interactive: false);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
            prompter: $prompter,
        );

        ['exitCode' => $exitCode] = executeMigrateCommand($command, ['marko', 'db:migrate', '--force']);

        expect($exitCode)->toBe(0)
            ->and($prompter->asked)->toBeEmpty()
            ->and($generator->generateCalled)->toBeTrue();
    });

    it('generates destructive changes when the user confirms', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);
        $prompter = new FakeConfirmationPrompter(answers: [true]);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
            prompter: $prompter,
        );

        ['exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(0)
            ->and($prompter->asked)->toBe(['Generate a migration with these changes?'])
            ->and($generator->generateCalled)->toBeTrue();
    });

    it('does not generate destructive changes when the user declines', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);
        $prompter = new FakeConfirmationPrompter(answers: [false]);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
            prompter: $prompter,
        );

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Migration generation cancelled.')
            ->and($generator->generateCalled)->toBeFalse();
    });

    it('does not prompt when the diff has no destructive changes', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/new.php']);
        $prompter = new FakeConfirmationPrompter();

        $command = createMigrateCommand(
            generator: $generator,
            diff: createTableDiffForMigrate(),
            prompter: $prompter,
        );

        ['output' => $output] = executeMigrateCommand($command);

        expect($prompter->asked)->toBeEmpty()
            ->and($output)->not->toContain('remove existing database objects')
            ->and($generator->generateCalled)->toBeTrue();
    });

    it('treats dropped foreign keys as destructive', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);
        $diff = new SchemaDiff(
            tablesToAlter: [
                'posts' => new TableDiff(
                    tableName: 'posts',
                    foreignKeysToDrop: [
                        new ForeignKey(
                            name: 'fk_posts_user_id',
                            columns: ['user_id'],
                            referencedTable: 'users',
                            referencedColumns: ['id'],
                        ),
                    ],
                ),
            ],
        );

        $command = createMigrateCommand(generator: $generator, diff: $diff);

        ['exitCode' => $exitCode] = executeMigrateCommand($command);

        expect($exitCode)->toBe(1)
            ->and($generator->generateCalled)->toBeFalse();
    });

    it('applies the same confirmation when --generate is used outside development', function (): void {
        $generator = createMigrationGeneratorStub(generatedPaths: ['/app/database/migrations/drop.php']);

        $command = createMigrateCommand(
            generator: $generator,
            diff: createDestructiveDiffForMigrate(),
            sqlGenerator: createDestructiveSqlGeneratorForMigrate(),
            appEnv: 'production',
        );

        ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand(
            $command,
            ['marko', 'db:migrate', '--generate'],
        );

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Re-run with --force')
            ->and($generator->generateCalled)->toBeFalse();
    });
});

it('still lets the migrate command catch a migration capability exception as MigrationException', function (): void {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    $migrator = new class () extends Migrator
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct() {}

        public function getPending(): array
        {
            return ['2024_01_01_000000_add_partial_index'];
        }

        public function migrate(): array
        {
            throw MigrationException::partialIndexNotSupported('idx_active', 'MySQL');
        }
    };

    ['output' => $output, 'exitCode' => $exitCode] = executeMigrateCommand(createMigrateCommand(migrator: $migrator));

    expect($exitCode)->toBe(1)
        ->and($output)->toContain("Error: Index 'idx_active' declares a WHERE predicate");
});
