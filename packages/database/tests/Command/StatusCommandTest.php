<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Command\StatusCommand;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Command\Helpers;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Get the standard migration file content for testing.
 */
function getStatusMigrationContent(): string
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
 * Test context for StatusCommand tests.
 */
final class StatusTestContext
{
    public function __construct(
        public string $migrationsPath,
        public StatusCommand $command,
    ) {}

    /**
     * Execute the command and return output.
     *
     * @return array{output: string, exitCode: int}
     */
    public function execute(): array
    {
        ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
        $input = new Input(['marko', 'db:status']);

        $exitCode = $this->command->execute($input, $output);
        $result = Helpers::getOutputContent($stream);

        return ['output' => $result, 'exitCode' => $exitCode];
    }

    /**
     * Clean up the test context.
     */
    public function cleanup(): void
    {
        $files = glob($this->migrationsPath . '/*.php');
        if ($files) {
            array_map('unlink', $files);
        }
        rmdir($this->migrationsPath);
    }
}

/**
 * Create a stub DataMigrator for status testing.
 *
 * @param array<array{name: string, path: string, source: string}> $pendingMigrations
 * @param array<string> $appliedMigrations
 */
function createStatusDataMigratorStub(
    array $pendingMigrations = [],
    array $appliedMigrations = [],
): DataMigrator {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($pendingMigrations, $appliedMigrations) extends DataMigrator
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly array $pendingMigrations,
            private readonly array $appliedMigrations,
        ) {}

        public function migrate(): array
        {
            return array_column($this->pendingMigrations, 'name');
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
 * Set up a StatusCommand test with the given configuration.
 *
 * @param array<string> $migrationFiles Files that exist on disk
 * @param array<array{name: string, batch: int}> $appliedWithBatch Migrations marked as applied with batch
 * @param MigrationRepository&MockObject $repository The mock repository
 * @param array<array{name: string, path: string, source: string}> $dataPending Pending data migrations
 * @param array<string> $dataApplied Applied data migrations
 */
function setupStatusTest(
    array $migrationFiles,
    array $appliedWithBatch,
    MigrationRepository&MockObject $repository,
    array $dataPending = [],
    array $dataApplied = [],
): StatusTestContext {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . bin2hex(random_bytes(8));
    mkdir($migrationsPath, 0777, true);

    $content = getStatusMigrationContent();
    foreach ($migrationFiles as $name) {
        file_put_contents($migrationsPath . '/' . $name . '.php', $content);
    }

    $applied = array_column($appliedWithBatch, 'name');

    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn($appliedWithBatch);
    $repository->method('getApplied')->willReturn($applied);

    $connection = Helpers::createStubConnection();
    // Create a temp directory structure with database/migrations for ProjectPaths
    $basePath = dirname($migrationsPath);
    $databasePath = $basePath . '/database';
    if (!is_dir($databasePath)) {
        mkdir($databasePath, 0777, true);
    }
    rename($migrationsPath, $databasePath . '/migrations');
    $migrationsPath = $databasePath . '/migrations';

    $paths = new ProjectPaths($basePath);
    $migrator = new Migrator($connection, $repository, $paths);
    $dataMigrator = createStatusDataMigratorStub($dataPending, $dataApplied);
    $command = new StatusCommand($migrator, $dataMigrator, $repository, $connection);

    return new StatusTestContext($migrationsPath, $command);
}

it('registers as db:status command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(StatusCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:status');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(StatusCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('shows list of applied migrations with batch number', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
            ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
        ],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('2024_01_01_000000_create_users_table')
        ->and($output)->toContain('1')
        ->and($output)->toContain('2024_01_02_000000_create_posts_table')
        ->and($output)->toContain('2');

    $ctx->cleanup();
});

it('shows list of pending migrations', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_create_comments_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('2024_01_02_000000_create_posts_table')
        ->and($output)->toContain('2024_01_03_000000_create_comments_table')
        ->and($output)->toContain('Pending');

    $ctx->cleanup();
});

it('shows total count of applied migrations', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_create_comments_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
            ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
        ],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('Schema: 2 applied, 1 pending');

    $ctx->cleanup();
});

it('shows total count of pending migrations', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_create_comments_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('Schema: 1 applied, 2 pending');

    $ctx->cleanup();
});

it('shows "No migrations found" when migrations directory empty', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [],
        appliedWithBatch: [],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('No migrations found');

    $ctx->cleanup();
});

it('shows "All migrations applied" when no pending migrations', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
            ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
        ],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('All migrations applied');

    $ctx->cleanup();
});

it('returns 0 exit code on success', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [],
        appliedWithBatch: [],
        repository: $this->createMock(MigrationRepository::class),
    );

    ['exitCode' => $exitCode] = $ctx->execute();

    expect($exitCode)->toBe(0);

    $ctx->cleanup();
});

it('shows data migration status', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [],
        appliedWithBatch: [],
        repository: $this->createMock(MigrationRepository::class),
        dataPending: [
            ['name' => '001_seed_countries', 'path' => '/app/Data/001_seed_countries.php', 'source' => 'app/core'],
        ],
        dataApplied: ['000_seed_initial'],
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('Applied Data Migrations:')
        ->and($output)->toContain('000_seed_initial')
        ->and($output)->toContain('Pending Data Migrations:')
        ->and($output)->toContain('001_seed_countries')
        ->and($output)->toContain('Data: 1 applied, 1 pending');

    $ctx->cleanup();
});

it('shows both schema and data migration summary', function (): void {
    $ctx = setupStatusTest(
        migrationFiles: [
            '2024_01_01_000000_create_users_table',
        ],
        appliedWithBatch: [
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ],
        repository: $this->createMock(MigrationRepository::class),
        dataApplied: ['001_seed_countries'],
    );

    ['output' => $output] = $ctx->execute();

    expect($output)->toContain('Schema: 1 applied, 0 pending')
        ->and($output)->toContain('Data: 1 applied, 0 pending')
        ->and($output)->toContain('All migrations applied');

    $ctx->cleanup();
});
