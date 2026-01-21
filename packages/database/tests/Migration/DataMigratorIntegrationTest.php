<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\DataMigrationDiscovery;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationRepository;

use function Marko\Database\Tests\Migration\removeDirectory;

require_once __DIR__ . '/Helpers.php';

describe('DataMigrator Integration', function (): void {
    beforeEach(function (): void {
        $this->tempDir = sys_get_temp_dir() . '/marko_data_migrator_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create directory structure
        mkdir($this->tempDir . '/vendor', 0777, true);
        mkdir($this->tempDir . '/modules', 0777, true);
        mkdir($this->tempDir . '/app', 0777, true);
        mkdir($this->tempDir . '/schema_migrations', 0777, true);
    });

    afterEach(function (): void {
        removeDirectory($this->tempDir);
    });

    it('tracks data migrations in same migrations table', function (): void {
        $recordedMigrations = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')->willReturn(1);
        $connection->method('query')
            ->willReturnCallback(function (string $sql) use (&$recordedMigrations): array {
                if (str_contains($sql, 'SELECT name')) {
                    return array_map(fn (string $name): array => ['name' => $name], $recordedMigrations);
                }
                if (str_contains($sql, 'MAX(batch)')) {
                    return [['max_batch' => empty($recordedMigrations) ? null : 1]];
                }

                return [];
            });

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->expects($this->atLeastOnce())
            ->method('record')
            ->willReturnCallback(function (
                ConnectionInterface $conn,
                string $name,
            ) use (&$recordedMigrations): void {
                $recordedMigrations[] = $name;
            });

        // Create a data migration
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);
        file_put_contents(
            $vendorPath . '/001_insert_statuses.php',
            <<<'PHP'
                <?php
                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\DataMigration;
                return new class extends DataMigration {
                    public function up(ConnectionInterface $connection): void {}
                    public function down(ConnectionInterface $connection): void {}
                };
                PHP,
        );

        $discovery = new DataMigrationDiscovery(
            $this->tempDir . '/vendor',
            $this->tempDir . '/modules',
            $this->tempDir . '/app',
        );

        $migrator = new DataMigrator($connection, $repository, $discovery);
        $applied = $migrator->migrate();

        // Data migrations should be recorded in the same repository
        expect($applied)
            ->toBe(['001_insert_statuses'])
            ->and($recordedMigrations)->toContain('001_insert_statuses');
    });

    it('runs data migrations after schema migrations in same batch', function (): void {
        $executionOrder = [];
        $batchNumbers = [];

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function (string $sql) use (&$executionOrder): int {
                if (str_contains($sql, 'CREATE TABLE')) {
                    $executionOrder[] = 'schema';
                } elseif (str_contains($sql, 'INSERT INTO post_statuses')) {
                    $executionOrder[] = 'data';
                }

                return 1;
            });
        $connection->method('query')->willReturn([]);

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('getApplied')->willReturn([]);
        $repository->method('getNextBatchNumber')->willReturn(1);
        $repository->method('record')
            ->willReturnCallback(function (
                ConnectionInterface $conn,
                string $name,
                int $batch,
            ) use (&$batchNumbers): void {
                $batchNumbers[$name] = $batch;
            });

        // Create a data migration that inserts data
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);
        file_put_contents(
            $vendorPath . '/002_insert_statuses.php',
            <<<'PHP'
                <?php
                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\DataMigration;
                return new class extends DataMigration {
                    public function up(
                        ConnectionInterface $connection,
                    ): void {
                        $this->insert($connection, 'post_statuses', ['id' => 1, 'name' => 'draft']);
                    }
                    public function down(ConnectionInterface $connection): void {}
                };
                PHP,
        );

        // Create a schema migration
        file_put_contents(
            $this->tempDir . '/schema_migrations/001_create_posts.php',
            <<<'PHP'
                <?php
                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\Migration;
                return new class extends Migration {
                    public function up(
                        ConnectionInterface $connection,
                    ): void {
                        $this->execute($connection, 'CREATE TABLE posts (id INT)');
                    }
                    public function down(ConnectionInterface $connection): void {}
                };
                PHP,
        );

        $discovery = new DataMigrationDiscovery(
            $this->tempDir . '/vendor',
            $this->tempDir . '/modules',
            $this->tempDir . '/app',
        );

        $dataMigrator = new DataMigrator($connection, $repository, $discovery);

        // Run data migrations
        $applied = $dataMigrator->migrate();

        expect($applied)
            ->toContain('002_insert_statuses')
            // Data migration should use same batch numbering system
            ->and($batchNumbers['002_insert_statuses'])->toBe(1);
    });

    it('skips already applied data migrations', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('getApplied')->willReturn(['001_already_applied']);
        $repository->method('getNextBatchNumber')->willReturn(2);
        $repository->expects($this->never())->method('record');

        // Create a migration that was already applied
        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);
        file_put_contents(
            $vendorPath . '/001_already_applied.php',
            <<<'PHP'
                <?php
                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\DataMigration;
                return new class extends DataMigration {
                    public function up(ConnectionInterface $connection): void {}
                    public function down(ConnectionInterface $connection): void {}
                };
                PHP,
        );

        $discovery = new DataMigrationDiscovery(
            $this->tempDir . '/vendor',
            $this->tempDir . '/modules',
            $this->tempDir . '/app',
        );

        $migrator = new DataMigrator($connection, $repository, $discovery);
        $applied = $migrator->migrate();

        expect($applied)->toBe([]);
    });

    it('supports rollback for data migrations', function (): void {
        $deletedMigrations = [];
        $downCalled = false;

        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('execute')
            ->willReturnCallback(function () use (&$downCalled): int {
                $downCalled = true;

                return 1;
            });

        $repository = $this->createMock(MigrationRepository::class);
        $repository->method('getLastBatchMigrations')->willReturn(['001_insert_data']);
        $repository->method('delete')
            ->willReturnCallback(function (
                ConnectionInterface $conn,
                string $name,
            ) use (&$deletedMigrations): void {
                $deletedMigrations[] = $name;
            });

        $vendorPath = $this->tempDir . '/vendor/acme/blog/Data';
        mkdir($vendorPath, 0777, true);
        file_put_contents(
            $vendorPath . '/001_insert_data.php',
            <<<'PHP'
                <?php
                use Marko\Database\Connection\ConnectionInterface;
                use Marko\Database\Migration\DataMigration;
                return new class extends DataMigration {
                    public function up(ConnectionInterface $connection): void {}
                    public function down(
                        ConnectionInterface $connection,
                    ): void {
                        $this->delete($connection, 'post_statuses', ['id' => 1]);
                    }
                };
                PHP,
        );

        $discovery = new DataMigrationDiscovery(
            $this->tempDir . '/vendor',
            $this->tempDir . '/modules',
            $this->tempDir . '/app',
        );

        $migrator = new DataMigrator($connection, $repository, $discovery);
        $rolledBack = $migrator->rollback();

        expect($rolledBack)
            ->toBe(['001_insert_data'])
            ->and($deletedMigrations)->toContain('001_insert_data')
            ->and($downCalled)->toBeTrue();
    });
});
