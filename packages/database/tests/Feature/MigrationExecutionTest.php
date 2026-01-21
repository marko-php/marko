<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use RuntimeException;

describe('Migration Execution', function (): void {
    beforeEach(function (): void {
        $this->migrationsPath = sys_get_temp_dir() . '/marko_migration_test_' . uniqid();
        mkdir($this->migrationsPath, 0777, true);
    });

    afterEach(function (): void {
        if (is_dir($this->migrationsPath)) {
            array_map('unlink', glob($this->migrationsPath . '/*.php'));
            rmdir($this->migrationsPath);
        }
    });

    it('applies and rolls back migrations correctly', function (): void {
        $appliedMigrations = [];
        $rolledBackMigrations = [];

        // Create test migration files
        $migrationUp = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('CREATE TABLE test_table');
    }
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('DROP TABLE test_table');
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000001_create_test.php', $migrationUp);

        // Mock connection that tracks executed statements
        $connection = new class ($appliedMigrations, $rolledBackMigrations) implements ConnectionInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$applied,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$rolledBack,
            ) {}

            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_starts_with($sql, 'CREATE')) {
                    $this->applied[] = $sql;
                } elseif (str_starts_with($sql, 'DROP')) {
                    $this->rolledBack[] = $sql;
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        // Mock repository
        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record');
        $repository->method('getLastBatchMigrations')->willReturn(['2024_01_01_000001_create_test']);
        $repository->method('delete');

        // Run migration
        $migrator = new Migrator($connection, $repository, $this->migrationsPath);
        $applied = $migrator->migrate();

        expect($applied)
            ->toHaveCount(1)
            ->and($applied[0])->toBe('2024_01_01_000001_create_test')
            ->and($appliedMigrations)->toContain('CREATE TABLE test_table');

        // Rollback
        $rolledBack = $migrator->rollback();

        expect($rolledBack)
            ->toHaveCount(1)
            ->and($rolledBackMigrations)->toContain('DROP TABLE test_table');
    });

    it('applies migrations in order', function (): void {
        $executionOrder = [];

        $migration1 = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('MIGRATION_1');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        $migration2 = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('MIGRATION_2');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000001_first.php', $migration1);
        file_put_contents($this->migrationsPath . '/2024_01_01_000002_second.php', $migration2);

        $connection = new class ($executionOrder) implements ConnectionInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_starts_with($sql, 'MIGRATION_')) {
                    $this->order[] = $sql;
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record');

        $migrator = new Migrator($connection, $repository, $this->migrationsPath);
        $migrator->migrate();

        expect($executionOrder)->toBe(['MIGRATION_1', 'MIGRATION_2']);
    });

    it('skips already applied migrations', function (): void {
        $executedMigrations = [];

        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('EXECUTED');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000001_first.php', $migrationContent);
        file_put_contents($this->migrationsPath . '/2024_01_01_000002_second.php', $migrationContent);

        $connection = new class ($executedMigrations) implements ConnectionInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$executed,
            ) {}

            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if ($sql === 'EXECUTED') {
                    $this->executed[] = 'executed';
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('createTable');
        // First migration already applied
        $repository->method('getApplied')->willReturn(['2024_01_01_000001_first']);
        $repository->method('getNextBatchNumber')->willReturn(2);
        $repository->method('record');

        $migrator = new Migrator($connection, $repository, $this->migrationsPath);
        $applied = $migrator->migrate();

        // Only second migration should be applied
        expect($applied)
            ->toHaveCount(1)
            ->and($applied[0])->toBe('2024_01_01_000002_second')
            ->and($executedMigrations)->toHaveCount(1);
    });

    it('throws MigrationException on migration failure', function (): void {
        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        throw new \RuntimeException('Database error');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000001_failing.php', $migrationContent);

        $connection = $this->createMock(ConnectionInterface::class);

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);

        $migrator = new Migrator($connection, $repository, $this->migrationsPath);

        expect(fn () => $migrator->migrate())
            ->toThrow(MigrationException::class, '2024_01_01_000001_failing');
    });

    it('rolls back migrations in reverse order', function (): void {
        $rollbackOrder = [];

        $migration1 = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('ROLLBACK_1');
    }
};
PHP;

        $migration2 = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('ROLLBACK_2');
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000001_first.php', $migration1);
        file_put_contents($this->migrationsPath . '/2024_01_01_000002_second.php', $migration2);

        $connection = new class ($rollbackOrder) implements ConnectionInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$order,
            ) {}

            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if (str_starts_with($sql, 'ROLLBACK_')) {
                    $this->order[] = $sql;
                }

                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('createTable');
        // Return migrations in reverse order (as they would be in batch)
        $repository->method('getLastBatchMigrations')->willReturn([
            '2024_01_01_000002_second',
            '2024_01_01_000001_first',
        ]);
        $repository->method('delete');

        $migrator = new Migrator($connection, $repository, $this->migrationsPath);
        $migrator->rollback();

        expect($rollbackOrder)->toBe(['ROLLBACK_2', 'ROLLBACK_1']);
    });
});
