<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Migration\Helpers;

describe('Migrator', function (): void {
    beforeEach(function (): void {
        // Create a temp directory structure with database/migrations for ProjectPaths
        $this->basePath = sys_get_temp_dir() . '/marko_test_base_' . bin2hex(random_bytes(8));
        $this->migrationsPath = $this->basePath . '/database/migrations';
        mkdir($this->migrationsPath, 0777, true);
        $this->paths = new ProjectPaths($this->basePath);
    });

    afterEach(function (): void {
        // Clean up temp directory
        Helpers::removeDirectory($this->basePath);
    });

    it('creates migrations table if not exists', function (): void {
        $tableCreated = false;

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createMock(MigrationRepository::class);
        $repository->expects($this->once())
            ->method('createTable')
            ->with($connection)
            ->willReturnCallback(function () use (&$tableCreated): void {
                $tableCreated = true;
            });
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->migrate();

        expect($tableCreated)->toBeTrue();
    });

    it('finds pending migration files in database/migrations/', function (): void {
        $migrationContent = Helpers::getEmptyMigrationContent();

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_create_users_table.php', $migrationContent);
        file_put_contents($this->migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record');

        $migrator = new Migrator($connection, $repository, $this->paths);
        $pending = $migrator->getPending();

        expect($pending)
            ->toHaveCount(2)
            ->and($pending[0])->toBe('2024_01_01_000000_create_users_table')
            ->and($pending[1])->toBe('2024_01_02_000000_create_posts_table');
    });

    it('applies migrations in filename order', function (): void {
        $appliedOrder = [];

        // Create migration files out of order
        $migration1Content = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('CREATE TABLE users');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        $migration2Content = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('CREATE TABLE posts');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_02_000000_create_posts_table.php', $migration2Content);
        file_put_contents($this->migrationsPath . '/2024_01_01_000000_create_users_table.php', $migration1Content);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$appliedOrder): int {
                $appliedOrder[] = $sql;

                return 1;
            });

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record');

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->migrate();

        expect($appliedOrder)->toBe([
            'CREATE TABLE users',
            'CREATE TABLE posts',
        ]);
    });

    it('executes migration up() method', function (): void {
        $upExecuted = false;

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

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$upExecuted): int {
                if ($sql === 'EXECUTED') {
                    $upExecuted = true;
                }

                return 1;
            });

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record');

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->migrate();

        expect($upExecuted)->toBeTrue();
    });

    it('records applied migration with batch number', function (): void {
        $recordedMigrations = [];

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', Helpers::getEmptyMigrationContent());

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(5);
        $repository->method('record')
            ->willReturnCallback(function ($conn, $name, $batch) use (&$recordedMigrations): void {
                $recordedMigrations[] = ['name' => $name, 'batch' => $batch];
            });

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->migrate();

        expect($recordedMigrations)
            ->toHaveCount(1)
            ->and($recordedMigrations[0]['name'])->toBe('2024_01_01_000000_test')
            ->and($recordedMigrations[0]['batch'])->toBe(5);
    });

    it('groups migrations applied together in same batch', function (): void {
        $recordedBatches = [];
        Helpers::writeTestMigrationFiles($this->migrationsPath);

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(2);
        $repository->method('record')
            ->willReturnCallback(function ($conn, $name, $batch) use (&$recordedBatches): void {
                $recordedBatches[] = $batch;
            });

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->migrate();

        expect($recordedBatches)->toBe([2, 2, 2]);
    });

    it('rolls back last batch of migrations', function (): void {
        $rolledBack = [];

        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('ROLLBACK');
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_first.php', $migrationContent);
        file_put_contents($this->migrationsPath . '/2024_01_02_000000_second.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$rolledBack): int {
                if ($sql === 'ROLLBACK') {
                    $rolledBack[] = 'rolled';
                }

                return 1;
            });

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn([
            '2024_01_02_000000_second',
            '2024_01_01_000000_first',
        ]);
        $repository->method('delete');

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->rollback();

        expect($rolledBack)->toHaveCount(2);
    });

    it('executes migration down() method on rollback', function (): void {
        $downExecuted = false;

        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('DOWN_EXECUTED');
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$downExecuted): int {
                if ($sql === 'DOWN_EXECUTED') {
                    $downExecuted = true;
                }

                return 1;
            });

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn(['2024_01_01_000000_test']);
        $repository->method('delete');

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->rollback();

        expect($downExecuted)->toBeTrue();
    });

    it('removes migration record after rollback', function (): void {
        $deletedMigrations = [];

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', Helpers::getEmptyMigrationContent());

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn(['2024_01_01_000000_test']);
        $repository->method('delete')
            ->willReturnCallback(function ($conn, $name) use (&$deletedMigrations): void {
                $deletedMigrations[] = $name;
            });

        $migrator = new Migrator($connection, $repository, $this->paths);
        $migrator->rollback();

        expect($deletedMigrations)->toBe(['2024_01_01_000000_test']);
    });

    it('returns list of applied migrations', function (): void {
        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ]);

        $migrator = new Migrator($connection, $repository, $this->paths);
        $applied = $migrator->getApplied();

        expect($applied)->toBe([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
        ]);
    });

    it('returns list of pending migrations', function (): void {
        Helpers::writeTestMigrationFiles($this->migrationsPath);

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([
            '2024_01_01_000000_first',
        ]);

        $migrator = new Migrator($connection, $repository, $this->paths);
        $pending = $migrator->getPending();

        expect($pending)->toBe([
            '2024_01_02_000000_second',
            '2024_01_03_000000_third',
        ]);
    });

    it('throws MigrationException on failure', function (): void {
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

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);

        $migrator = new Migrator($connection, $repository, $this->paths);

        expect(fn () => $migrator->migrate())
            ->toThrow(MigrationException::class, '2024_01_01_000000_test');
    });

    it('keeps the exception a migration threw as the previous exception', function (): void {
        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(
        ConnectionInterface $connection,
    ): void {
        throw new \DomainException('Database error');
    }
    public function down(ConnectionInterface $connection): void {}
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_test.php', $migrationContent);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);

        $migrator = new Migrator($this->createStub(ConnectionInterface::class), $repository, $this->paths);

        try {
            $migrator->migrate();
            $caught = null;
        } catch (MigrationException $e) {
            $caught = $e;
        }

        expect($caught)->toBeInstanceOf(MigrationException::class)
            ->and($caught?->getPrevious())->toBeInstanceOf(DomainException::class);
    });

    it('throws MigrationException when migration file not found', function (): void {
        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn(['2024_01_01_000000_nonexistent']);

        $migrator = new Migrator($connection, $repository, $this->paths);

        expect(fn () => $migrator->rollback())
            ->toThrow(MigrationException::class, 'not found');
    });

    it('resets database by rolling back all migrations in reverse order', function (): void {
        $rolledBackOrder = [];

        $migrationContent = <<<'PHP'
<?php
declare(strict_types=1);
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\Migration;
return new class () extends Migration {
    public function up(ConnectionInterface $connection): void {}
    public function down(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('ROLLBACK');
    }
};
PHP;

        file_put_contents($this->migrationsPath . '/2024_01_01_000000_first.php', $migrationContent);
        file_put_contents($this->migrationsPath . '/2024_01_02_000000_second.php', $migrationContent);
        file_put_contents($this->migrationsPath . '/2024_01_03_000000_third.php', $migrationContent);

        $connection = $this->createStub(ConnectionInterface::class);
        $connection->method('execute')->willReturn(1);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([
            '2024_01_01_000000_first',
            '2024_01_02_000000_second',
            '2024_01_03_000000_third',
        ]);
        $repository->method('delete')
            ->willReturnCallback(function ($conn, $name) use (&$rolledBackOrder): void {
                $rolledBackOrder[] = $name;
            });

        $migrator = new Migrator($connection, $repository, $this->paths);
        $result = $migrator->reset();

        // Should roll back in reverse order (newest first)
        expect($rolledBackOrder)->toBe([
            '2024_01_03_000000_third',
            '2024_01_02_000000_second',
            '2024_01_01_000000_first',
        ])
            ->and($result)->toBe([
                '2024_01_03_000000_third',
                '2024_01_02_000000_second',
                '2024_01_01_000000_first',
            ]);
    });

    it('refuses to roll back a migration name that traverses outside the migrations directory', function (): void {
        // A planted file outside database/migrations that must never be required
        $marker = $this->basePath . '/storage/uploads/required.marker';
        mkdir($this->basePath . '/storage/uploads', 0777, true);
        file_put_contents(
            $this->basePath . '/storage/uploads/shell.php',
            "<?php\nfile_put_contents('$marker', 'pwned');\nreturn null;\n",
        );

        $connection = $this->createStub(ConnectionInterface::class);

        $deleted = [];
        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn(['../../storage/uploads/shell']);
        $repository->method('delete')
            ->willReturnCallback(function ($conn, $name) use (&$deleted): void {
                $deleted[] = $name;
            });

        $migrator = new Migrator($connection, $repository, $this->paths);

        expect(fn () => $migrator->rollback())
            ->toThrow(MigrationException::class, "Invalid migration name '../../storage/uploads/shell'")
            ->and(file_exists($marker))->toBeFalse()
            ->and($deleted)->toBe([]);
    });

    it('refuses to reset when a migration name traverses outside the migrations directory', function (): void {
        $marker = $this->basePath . '/storage/uploads/required.marker';
        mkdir($this->basePath . '/storage/uploads', 0777, true);
        file_put_contents(
            $this->basePath . '/storage/uploads/shell.php',
            "<?php\nfile_put_contents('$marker', 'pwned');\nreturn null;\n",
        );
        file_put_contents($this->migrationsPath . '/2024_01_01_000000_first.php', Helpers::getEmptyMigrationContent());

        $connection = $this->createStub(ConnectionInterface::class);

        $deleted = [];
        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getApplied')->willReturn([
            '2024_01_01_000000_first',
            '../../storage/uploads/shell',
        ]);
        $repository->method('delete')
            ->willReturnCallback(function ($conn, $name) use (&$deleted): void {
                $deleted[] = $name;
            });

        $migrator = new Migrator($connection, $repository, $this->paths);

        // Every name is resolved before any down() runs, so the legitimate migration is left untouched too
        expect(fn () => $migrator->reset())
            ->toThrow(MigrationException::class, "Invalid migration name '../../storage/uploads/shell'")
            ->and(file_exists($marker))->toBeFalse()
            ->and($deleted)->toBe([]);
    });

    it('rejects migration names containing path separators, parent segments or NUL bytes', function (
        string $name,
    ): void {
        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn([$name]);

        $migrator = new Migrator($connection, $repository, $this->paths);

        expect(fn () => $migrator->rollback())
            ->toThrow(MigrationException::class, 'Invalid migration name');
    })->with([
        'forward slash' => ['sub/2024_01_01_000000_first'],
        'backslash' => ['sub\\2024_01_01_000000_first'],
        'parent segment' => ['..'],
        'embedded parent segment' => ['2024..first'],
        'NUL byte' => ["2024_01_01_000000_first\0"],
    ]);

    it('refuses to roll back a name that is not a migration file found by the directory scan', function (): void {
        // A dotfile sits at the built path, but the *.php glob never lists it as a migration
        $marker = $this->migrationsPath . '/required.marker';
        file_put_contents(
            $this->migrationsPath . '/.hidden.php',
            "<?php\nfile_put_contents('$marker', 'pwned');\nreturn null;\n",
        );

        $connection = $this->createStub(ConnectionInterface::class);

        $repository = $this->createStub(MigrationRepository::class);
        $repository->method('createTable');
        $repository->method('getLastBatchMigrations')->willReturn(['.hidden']);

        $migrator = new Migrator($connection, $repository, $this->paths);

        expect(fn () => $migrator->rollback())
            ->toThrow(MigrationException::class, "Migration file for '.hidden' not found")
            ->and(file_exists($marker))->toBeFalse();
    });
});
