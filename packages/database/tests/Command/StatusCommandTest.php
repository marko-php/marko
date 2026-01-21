<?php

declare(strict_types=1);

require_once __DIR__ . '/Helpers.php';

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\StatusCommand;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;

use function Marko\Database\Tests\Command\createOutputStream;
use function Marko\Database\Tests\Command\createStubConnection;
use function Marko\Database\Tests\Command\getOutputContent;

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
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(ConnectionInterface $connection): void {}
};
PHP;

    file_put_contents($migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([
        ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
    ]);
    $repository->method('getApplied')->willReturn([
        '2024_01_01_000000_create_users_table',
        '2024_01_02_000000_create_posts_table',
    ]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('2024_01_01_000000_create_users_table')
        ->and($result)->toContain('1')
        ->and($result)->toContain('2024_01_02_000000_create_posts_table')
        ->and($result)->toContain('2');

    // Clean up
    array_map('unlink', glob($migrationsPath . '/*.php'));
    rmdir($migrationsPath);
});

it('shows list of pending migrations', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(ConnectionInterface $connection): void {}
};
PHP;

    file_put_contents($migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_03_000000_create_comments_table.php', $migrationContent);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([
        ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
    ]);
    $repository->method('getApplied')->willReturn([
        '2024_01_01_000000_create_users_table',
    ]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('2024_01_02_000000_create_posts_table')
        ->and($result)->toContain('2024_01_03_000000_create_comments_table')
        ->and($result)->toContain('Pending');

    // Clean up
    array_map('unlink', glob($migrationsPath . '/*.php'));
    rmdir($migrationsPath);
});

it('shows total count of applied migrations', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(ConnectionInterface $connection): void {}
};
PHP;

    file_put_contents($migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_03_000000_create_comments_table.php', $migrationContent);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([
        ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
    ]);
    $repository->method('getApplied')->willReturn([
        '2024_01_01_000000_create_users_table',
        '2024_01_02_000000_create_posts_table',
    ]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('Applied: 2');

    // Clean up
    array_map('unlink', glob($migrationsPath . '/*.php'));
    rmdir($migrationsPath);
});

it('shows total count of pending migrations', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(ConnectionInterface $connection): void {}
};
PHP;

    file_put_contents($migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_03_000000_create_comments_table.php', $migrationContent);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([
        ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
    ]);
    $repository->method('getApplied')->willReturn([
        '2024_01_01_000000_create_users_table',
    ]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('Pending: 2');

    // Clean up
    array_map('unlink', glob($migrationsPath . '/*.php'));
    rmdir($migrationsPath);
});

it('shows "No migrations found" when migrations directory empty', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([]);
    $repository->method('getApplied')->willReturn([]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('No migrations found');

    // Clean up
    rmdir($migrationsPath);
});

it('shows "All migrations applied" when no pending migrations', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(ConnectionInterface $connection): void {}
};
PHP;

    file_put_contents($migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
    file_put_contents($migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([
        ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
        ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 2],
    ]);
    $repository->method('getApplied')->willReturn([
        '2024_01_01_000000_create_users_table',
        '2024_01_02_000000_create_posts_table',
    ]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('All migrations applied');

    // Clean up
    array_map('unlink', glob($migrationsPath . '/*.php'));
    rmdir($migrationsPath);
});

it('returns 0 exit code on success', function (): void {
    $migrationsPath = sys_get_temp_dir() . '/marko_status_test_' . uniqid();
    mkdir($migrationsPath, 0777, true);

    $connection = createStubConnection();

    $repository = $this->createMock(MigrationRepository::class);
    $repository->method('createTable');
    $repository->method('getAppliedWithBatch')->willReturn([]);
    $repository->method('getApplied')->willReturn([]);

    $migrator = new Migrator($connection, $repository, $migrationsPath);

    $command = new StatusCommand($migrator, $repository, $connection);

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:status']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);

    // Clean up
    rmdir($migrationsPath);
});
