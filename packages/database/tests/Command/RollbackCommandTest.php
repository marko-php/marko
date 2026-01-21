<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Database\Command\RollbackCommand;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Command\Helpers;

/**
 * Helper to create a stub Migrator.
 *
 * @param array<string> $lastBatchMigrations
 * @param array<int, array<string>> $batchesMigrations
 *
 * @return Migrator&object{rolledBack: array<string>, rollbackCallCount: int}
 */
function createStubMigrator(
    array $lastBatchMigrations = [],
    array $batchesMigrations = [],
    bool $shouldFail = false,
    string $failMessage = 'Migration failed',
): Migrator {
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($lastBatchMigrations, $batchesMigrations, $shouldFail, $failMessage) extends Migrator
    {
        /** @var array<string> */
        public array $rolledBack = [];

        /** @var int */
        public int $rollbackCallCount = 0;

        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly array $lastBatchMigrations,
            private readonly array $batchesMigrations,
            private readonly bool $shouldFail,
            private readonly string $failMessage,
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

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('db:rollback');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(RollbackCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('blocks execution in production environment', function (): void {
    /** @var Migrator&object{rolledBack: array<string>, rollbackCallCount: int} $migrator */
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: true,
    );

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1)
        ->and($migrator->rollbackCallCount)->toBe(0);
});

it('shows error message when blocked in production', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('cannot be run in production')
        ->and($result)->toContain('Rollback is never allowed in production');
});

it('does NOT support --force flag (rollback is never allowed in production)', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: true,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    // Even with --force, it should still block
    $input = new Input(['marko', 'db:rollback', '--force']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(1);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('cannot be run in production');
});

it('rolls back last batch of migrations in development', function (): void {
    /** @var Migrator&object{rolledBack: array<string>, rollbackCallCount: int} $migrator */
    $migrator = createStubMigrator(
        lastBatchMigrations: [
            '2024_01_02_000000_second',
            '2024_01_01_000000_first',
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0)
        ->and($migrator->rollbackCallCount)->toBe(1)
        ->and($migrator->rolledBack)->toBe([
            '2024_01_02_000000_second',
            '2024_01_01_000000_first',
        ]);
});

it('executes down() in reverse order within batch', function (): void {
    // The Migrator already handles reverse order via MigrationRepository
    // This test verifies the command uses the migrator correctly
    /** @var Migrator&object{rolledBack: array<string>, rollbackCallCount: int} $migrator */
    $migrator = createStubMigrator(
        lastBatchMigrations: [
            '2024_01_03_000000_third',  // rolled back first
            '2024_01_02_000000_second', // rolled back second
            '2024_01_01_000000_first',  // rolled back third
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['output' => $output] = Helpers::createOutputStream();
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
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('Rolling back: 2024_01_02_000000_create_posts_table')
        ->and($result)->toContain('Rolling back: 2024_01_01_000000_create_users_table');
});

it('removes migration records from tracking table', function (): void {
    // This is handled by Migrator.rollback() internally
    // The command test verifies rollback() is called
    /** @var Migrator&object{rolledBack: array<string>, rollbackCallCount: int} $migrator */
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    expect($migrator->rollbackCallCount)->toBe(1);
});

it('supports --step option to rollback multiple batches', function (): void {
    /** @var Migrator&object{rolledBack: array<string>, rollbackCallCount: int} $migrator */
    $migrator = createStubMigrator(
        batchesMigrations: [
            ['2024_01_03_000000_third'],
            ['2024_01_02_000000_second'],
            ['2024_01_01_000000_first'],
        ],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback', '--step=2']);

    $command->execute($input, $output);

    expect($migrator->rollbackCallCount)->toBe(2)
        ->and($migrator->rolledBack)->toBe([
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
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    // Should mention uncommitted files hint
    expect($result)->toContain('uncommitted migration files');
});

it('shows "Nothing to rollback" when no applied migrations', function (): void {
    $migrator = createStubMigrator();

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

    expect($result)->toContain('Nothing to rollback');
});

it('warns about entity sync after rollback', function (): void {
    $migrator = createStubMigrator(
        lastBatchMigrations: ['2024_01_01_000000_test'],
    );

    $command = new RollbackCommand(
        migrator: $migrator,
        isProduction: false,
    );

    ['stream' => $stream, 'output' => $output] = Helpers::createOutputStream();
    $input = new Input(['marko', 'db:rollback']);

    $command->execute($input, $output);

    $result = Helpers::getOutputContent($stream);

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
        isProduction: false,
    );

    ['output' => $output] = Helpers::createOutputStream();
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
        isProduction: false,
    );

    ['output' => $output2] = Helpers::createOutputStream();
    $input2 = new Input(['marko', 'db:rollback']);

    $exitCode2 = $failingCommand->execute($input2, $output2);

    expect($exitCode2)->toBe(1);
});
