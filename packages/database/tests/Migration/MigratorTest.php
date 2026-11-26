<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\Tests\Migration\Helpers;
use Marko\Database\Tests\Migration\PluginProxiedConnection;
use Marko\Database\Tests\Migration\TransactionalConnectionFake;

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

    describe('transactions', function (): void {
        it(
            'runs each pending migration with its record in its own transaction when the connection supports transactional DDL',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE a'),
                );
                file_put_contents(
                    $this->migrationsPath . '/2024_01_02_000000_second.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE b'),
                );
                $connection = new TransactionalConnectionFake();

                new Migrator($connection, new MigrationRepository(), $this->paths)->migrate();

                expect($connection->transactions)->toHaveCount(2)
                    ->and($connection->transactions[0])->toHaveCount(2)
                    ->and($connection->transactions[0][0])->toBe('CREATE TABLE a')
                    ->and($connection->transactions[0][1])->toContain('INSERT INTO "migrations"')
                    ->and($connection->transactions[1])->toHaveCount(2)
                    ->and($connection->transactions[1][0])->toBe('CREATE TABLE b')
                    ->and($connection->transactions[1][1])->toContain('INSERT INTO "migrations"');
            },
        );

        it('does not record a migration whose up fails inside the transaction', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a', failAfterStatement: true),
            );
            $connection = new TransactionalConnectionFake();
            $migrator = new Migrator($connection, new MigrationRepository(), $this->paths);

            expect(fn () => $migrator->migrate())
                ->toThrow(MigrationException::class, 'boom')
                ->and($connection->transactions)->toHaveCount(1)
                ->and($connection->committed)->not->toContain('CREATE TABLE a')
                ->and(implode("\n", $connection->committed))->not->toContain('INSERT INTO');
        });

        it('runs a rollback and its delete in one transaction', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a', 'DROP TABLE a'),
            );
            $connection = new TransactionalConnectionFake(applied: ['2024_01_01_000000_first']);

            new Migrator($connection, new MigrationRepository(), $this->paths)->rollback();

            expect($connection->transactions)->toHaveCount(1)
                ->and($connection->transactions[0])->toHaveCount(2)
                ->and($connection->transactions[0][0])->toBe('DROP TABLE a')
                ->and($connection->transactions[0][1])->toContain('DELETE FROM "migrations"');
        });

        it('runs a reset one transaction per migration', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a', 'DROP TABLE a'),
            );
            file_put_contents(
                $this->migrationsPath . '/2024_01_02_000000_second.php',
                Helpers::getStatementMigrationContent('CREATE TABLE b', 'DROP TABLE b'),
            );
            $connection = new TransactionalConnectionFake(
                applied: ['2024_01_01_000000_first', '2024_01_02_000000_second'],
            );

            new Migrator($connection, new MigrationRepository(), $this->paths)->reset();

            expect($connection->transactions)->toHaveCount(2)
                ->and($connection->transactions[0])->toHaveCount(2)
                ->and($connection->transactions[0][0])->toBe('DROP TABLE b')
                ->and($connection->transactions[0][1])->toContain('DELETE FROM "migrations"')
                ->and($connection->transactions[1])->toHaveCount(2)
                ->and($connection->transactions[1][0])->toBe('DROP TABLE a')
                ->and($connection->transactions[1][1])->toContain('DELETE FROM "migrations"');
        });

        it('runs without a transaction on a connection that reports no transactional DDL support', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a'),
            );
            $connection = new TransactionalConnectionFake(supportsTransactionalDdl: false);

            new Migrator($connection, new MigrationRepository(), $this->paths)->migrate();

            expect($connection->transactions)->toBeEmpty()
                ->and($connection->committed)->toContain('CREATE TABLE a');
        });

        it('runs a migration marked WithoutTransaction outside a transaction', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a', withoutTransaction: true),
            );
            $connection = new TransactionalConnectionFake();

            new Migrator($connection, new MigrationRepository(), $this->paths)->migrate();

            expect($connection->transactions)->toBeEmpty()
                ->and($connection->committed)->toContain('CREATE TABLE a');
        });

        it(
            'runs in a transaction on a plugin-proxied connection whose target supports transactional DDL',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE a'),
                );
                $target = new TransactionalConnectionFake();
                $proxy = new PluginProxiedConnection($target);

                new Migrator($proxy, new MigrationRepository(), $this->paths)->migrate();

                expect($target->transactions)->toHaveCount(1)
                    ->and($target->transactions[0][0])->toBe('CREATE TABLE a')
                    ->and($proxy->seen)->toContain('CREATE TABLE a');
            },
        );

        it('says the migration was rolled back when it failed inside a transaction', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent('CREATE TABLE a', failAfterStatement: true),
            );
            $migrator = new Migrator(new TransactionalConnectionFake(), new MigrationRepository(), $this->paths);

            expect(fn () => $migrator->migrate())
                ->toThrow(
                    MigrationException::class,
                    "Migration '2024_01_01_000000_first' failed: boom. The migration was rolled back: nothing from it "
                    . 'was applied and it was not recorded. Fix it and run the migrations again.',
                );
        });

        it(
            'says the migration may be partly applied when the connection has no transactional DDL',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE a', failAfterStatement: true),
                );
                $connection = new TransactionalConnectionFake(supportsTransactionalDdl: false);
                $migrator = new Migrator($connection, new MigrationRepository(), $this->paths);

                expect(fn () => $migrator->migrate())
                    ->toThrow(
                        MigrationException::class,
                        "Migration '2024_01_01_000000_first' failed: boom. The migration ran without a transaction, so "
                        . 'statements before the failing one stay applied. It was not recorded. Check the database '
                        . 'and repair it before running the migrations again.',
                    );
            },
        );

        it('says the migration may be partly applied when a WithoutTransaction migration fails', function (): void {
            file_put_contents(
                $this->migrationsPath . '/2024_01_01_000000_first.php',
                Helpers::getStatementMigrationContent(
                    'CREATE TABLE a',
                    failAfterStatement: true,
                    withoutTransaction: true,
                ),
            );
            $migrator = new Migrator(new TransactionalConnectionFake(), new MigrationRepository(), $this->paths);

            expect(fn () => $migrator->migrate())
                ->toThrow(MigrationException::class, 'statements before the failing one stay applied');
        });

        it(
            'says the outcome is unknown when the connection is lost while committing the migration',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE a'),
                );
                $lost = LostConnectionException::duringTransaction(
                    new LostConnectionException(message: 'Database connection lost: server closed the connection'),
                );
                $connection = new TransactionalConnectionFake(
                    commitFailure: new RuntimeException('commit failed', 0, $lost),
                );
                $migrator = new Migrator($connection, new MigrationRepository(), $this->paths);

                expect(fn () => $migrator->migrate())
                    ->toThrow(
                        MigrationException::class,
                        'The connection was lost while committing, so whether the migration was applied is unknown. '
                        . 'Check the schema and the migrations table before running the migrations again.',
                    );
            },
        );

        it(
            'suggests WithoutTransaction when the database refuses a statement inside a transaction block',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE INDEX CONCURRENTLY i ON t (c)'),
                );
                $refused = new QueryException(
                    message: 'CREATE INDEX CONCURRENTLY cannot run inside a transaction block',
                    sql: 'CREATE INDEX CONCURRENTLY i ON t (c)',
                    sqlState: '25001',
                );
                $connection = new TransactionalConnectionFake(
                    failingStatements: ['CREATE INDEX CONCURRENTLY i ON t (c)' => $refused],
                );
                $migrator = new Migrator($connection, new MigrationRepository(), $this->paths);

                expect(fn () => $migrator->migrate())
                    ->toThrow(
                        MigrationException::class,
                        'Mark the migration #[WithoutTransaction] to run it outside one',
                    );
            },
        );

        it(
            'explains an aborted transaction when the migration carried on after a failed statement',
            function (): void {
                file_put_contents(
                    $this->migrationsPath . '/2024_01_01_000000_first.php',
                    Helpers::getStatementMigrationContent('CREATE TABLE a'),
                );
                $aborted = new QueryException(
                    message: 'current transaction is aborted, commands ignored until end of transaction block',
                    sql: 'CREATE TABLE a',
                    sqlState: '25P02',
                );
                $connection = new TransactionalConnectionFake(failingStatements: ['CREATE TABLE a' => $aborted]);
                $migrator = new Migrator($connection, new MigrationRepository(), $this->paths);

                expect(fn () => $migrator->migrate())
                    ->toThrow(
                        MigrationException::class,
                        'An earlier statement in this migration failed and the migration caught that error and carried '
                        . 'on, which PostgreSQL does not allow inside a transaction. Remove the catch (use IF EXISTS or '
                        . 'IF NOT EXISTS instead) or mark the migration #[WithoutTransaction].',
                    );
            },
        );
    });
});
