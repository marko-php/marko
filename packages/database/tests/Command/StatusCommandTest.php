<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\StatusCommand;
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
 * Set up a StatusCommand test with the given configuration.
 *
 * @param array<string> $migrationFiles Files that exist on disk
 * @param array<array{name: string, batch: int}> $appliedWithBatch Migrations marked as applied with batch
 * @param MigrationRepository&MockObject $repository The mock repository
 */
function setupStatusTest(
    array $migrationFiles,
    array $appliedWithBatch,
    MigrationRepository&MockObject $repository,
): StatusTestContext {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
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
    $migrator = new Migrator($connection, $repository, $migrationsPath);
    $command = new StatusCommand($migrator, $repository, $connection);

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

    expect($output)->toContain('Applied: 2');

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

    expect($output)->toContain('Pending: 2');

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
