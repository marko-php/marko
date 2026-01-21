<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\MigrateCommand;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

use function Marko\Database\Tests\Command\createOutputStream;
use function Marko\Database\Tests\Command\createStubEntityDiscovery;
use function Marko\Database\Tests\Command\createStubIntrospector;
use function Marko\Database\Tests\Command\getOutputContent;

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
    return new class ($upStatements) implements SqlGeneratorInterface
    {
        public function __construct(
            private readonly array $upStatements,
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
 * Helper to create a MigrateCommand with standard dependencies.
 *
 * @param Migrator&object{migrateApplied: array<string>, migrateCallCount: int, rollbackCalled: bool}|null $migrator
 * @param MigrationGenerator&object{generateCalled: bool}|null $generator
 */
function createMigrateCommand(
    ?Migrator $migrator = null,
    ?MigrationGenerator $generator = null,
    ?SchemaDiff $diff = null,
    ?SqlGeneratorInterface $sqlGenerator = null,
    bool $isProduction = false,
): MigrateCommand {
    return new MigrateCommand(
        migrator: $migrator ?? createMigratorStub(),
        migrationGenerator: $generator ?? createMigrationGeneratorStub(),
        entityDiscovery: createStubEntityDiscovery(),
        introspector: createStubIntrospector(),
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
        diffCalculator: createMigrateDiffCalculator($diff ?? new SchemaDiff()),
        sqlGenerator: $sqlGenerator ?? createMigrateSqlGenerator(),
        vendorPath: '/vendor',
        modulesPath: '/modules',
        appPath: '/app',
        isProduction: $isProduction,
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
    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = getOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('registers as db:migrate command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(MigrateCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:migrate');
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
        isProduction: true,
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

    expect($output)->toContain('3 migration(s)');
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
