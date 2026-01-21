<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Database\Command\RollbackCommand;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\Migrator;

if (!function_exists('createOutputStream')) {
    /**
     * Helper to capture output.
     *
     * @return array{stream: resource, output: Output}
     */
    function createOutputStream(): array
    {
        $stream = fopen('php://memory', 'r+');

        return [
            'stream' => $stream,
            'output' => new Output($stream),
        ];
    }
}

if (!function_exists('getOutputContent')) {
    /**
     * Helper to get output content.
     *
     * @param resource $stream
     */
    function getOutputContent(
        mixed $stream,
    ): string {
        rewind($stream);

        return stream_get_contents($stream);
    }
}

/**
 * Helper to create a stub Migrator.
 *
 * @param array<string> $lastBatchMigrations
 * @param array<int, array<string>> $batchesMigrations
 */
function createStubMigrator(
    array $lastBatchMigrations = [],
    array $batchesMigrations = [],
    bool $shouldFail = false,
    string $failMessage = 'Migration failed',
): Migrator {
    return new class ($lastBatchMigrations, $batchesMigrations, $shouldFail, $failMessage) extends Migrator
    {
        /** @var array<string> */
        public array $rolledBack = [];

        /** @var int */
        public int $rollbackCallCount = 0;

        public function __construct(
            private array $lastBatchMigrations,
            private array $batchesMigrations,
            private bool $shouldFail,
            private string $failMessage,
        ) {}

        public function rollback(): array
        {
            $this->rollbackCallCount++;

            if ($this->shouldFail) {
                throw new MigrationException($this->failMessage);
            }

            // Use batches if available, otherwise use lastBatchMigrations
            if (!empty($this->batchesMigrations)) {
                $batchIndex = $this->rollbackCallCount - 1;
                $migrations = $this->batchesMigrations[$batchIndex] ?? [];
            } else {
                $migrations = $this->rollbackCallCount === 1 ? $this->lastBatchMigrations : [];
            }

            $this->rolledBack = array_merge($this->rolledBack, $migrations);

            return $migrations;
        }

        public function getApplied(): array
        {
            return $this->lastBatchMigrations;
        }
    };
}

it('registers as db:rollback command via #[Command] attribute', function (): void {
    $reflection = new ReflectionClass(RollbackCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('db:rollback');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(RollbackCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('blocks execution in production environment', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1);
    expect($migrator->rollbackCallCount)->toBe(0);
});

it('shows error message when blocked in production', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('cannot be run in production')
        ->and($result)->toContain('Rollback is never allowed in production');
});

it('does NOT support --force flag (rollback is never allowed in production)', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    // Even with --force, it should still block
    $input = new Input(['marko', 'db:rollback', '--force']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1);

    $result = getOutputContent($stream);

    expect($result)->toContain('cannot be run in production');
});

it('rolls back last batch of migrations in development', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: [
            '2024_01_02_000000_second',
            '2024_01_01_000000_first',
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
    expect($migrator->rollbackCallCount)->toBe(1);
    expect($migrator->rolledBack)->toBe([
        '2024_01_02_000000_second',
        '2024_01_01_000000_first',
    ]);
});

it('executes down() in reverse order within batch', function (): void {
    // The Migrator already handles reverse order via MigrationRepository
    // This test verifies the command uses the migrator correctly
    $migrator = createStubMigrator(
        lastBatchMigrations: [
            '2024_01_03_000000_third',  // rolled back first
            '2024_01_02_000000_second', // rolled back second
            '2024_01_01_000000_first',  // rolled back third
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    // Migrator handles order, command just delegates
    expect($migrator->rolledBack)->toBe([
        '2024_01_03_000000_third',
        '2024_01_02_000000_second',
        '2024_01_01_000000_first',
    ]);
});

it('shows each migration being rolled back', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: [
            '2024_01_02_000000_create_posts_table',
            '2024_01_01_000000_create_users_table',
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('Rolling back: 2024_01_02_000000_create_posts_table')
        ->and($result)->toContain('Rolling back: 2024_01_01_000000_create_users_table');
});

it('removes migration records from tracking table', function (): void {
    // This is handled by Migrator.rollback() internally
    // The command test verifies rollback() is called
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    expect($migrator->rollbackCallCount)->toBe(1);
});

it('supports --step option to rollback multiple batches', function (): void {
    $migrator = createStubMigrator(
        batchesMigrations: [
            ['2024_01_03_000000_third'],
            ['2024_01_02_000000_second'],
            ['2024_01_01_000000_first'],
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback', '--step=2']);

    $command->execute($input, $output);

    expect($migrator->rollbackCallCount)->toBe(2);
    expect($migrator->rolledBack)->toBe([
        '2024_01_03_000000_third',
        '2024_01_02_000000_second',
    ]);
});

it('offers to delete uncommitted migration files', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    // Should mention uncommitted files hint
    expect($result)->toContain('uncommitted migration files');
});

it('shows "Nothing to rollback" when no applied migrations', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: [],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('Nothing to rollback');
});

it('warns about entity sync after rollback', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = getOutputContent($stream);

    expect($result)->toContain('entity')
        ->and($result)->toContain('sync');
});

it('returns 0 on success, 1 on failure', function (): void {
    // Success case
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);

    // Failure case
    $failingMigrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
        shouldFail: true,
        failMessage: 'Rollback failed',
    );

    $failingCommand = new RollbackCommand(
        migrator: $failingMigrator,
        migrationsPath: '/app/database/migrations',
        isProduction: false,
    );

    ['stream' => $stream2, 'output' => $output2] = createOutputStream();
    $input2 = new Input(['marko', 'db:rollback']);

    $exitCode2 = $failingCommand->execute($input2, $output2);

    expect($exitCode2)->toBe(1);
});
