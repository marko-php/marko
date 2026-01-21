<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Migration\MigrationRepository;

use function Marko\Database\Tests\Migration\createTrackingConnection;

require_once __DIR__ . '/Helpers.php';

describe('MigrationRepository', function (): void {
    it('creates migrations table if not exists', function (): void {
        $executedSql = [];
        $executedBindings = [];
        $connection = createTrackingConnection($executedSql, $executedBindings);

        $repository = new MigrationRepository();
        $repository->createTable($connection);

        expect($executedSql)
            ->toHaveCount(1)
            ->and($executedSql[0])->toContain('CREATE TABLE IF NOT EXISTS')
            ->and($executedSql[0])->toContain('migrations')
            ->and($executedSql[0])->toContain('name')
            ->and($executedSql[0])->toContain('batch');
    });

    it('records applied migration with batch number', function (): void {
        $executedSql = [];
        $executedBindings = [];
        $connection = createTrackingConnection($executedSql, $executedBindings);

        $repository = new MigrationRepository();
        $repository->record($connection, '2024_01_01_000000_create_users_table', 1);

        expect($executedSql)
            ->toHaveCount(1)
            ->and($executedSql[0])->toContain('INSERT INTO')
            ->and($executedSql[0])->toContain('migrations')
            ->and($executedBindings[0])->toBe(['2024_01_01_000000_create_users_table', 1]);
    });

    it('removes migration record after rollback', function (): void {
        $executedSql = [];
        $executedBindings = [];
        $connection = createTrackingConnection($executedSql, $executedBindings);

        $repository = new MigrationRepository();
        $repository->delete($connection, '2024_01_01_000000_create_users_table');

        expect($executedSql)
            ->toHaveCount(1)
            ->and($executedSql[0])->toContain('DELETE FROM')
            ->and($executedSql[0])->toContain('migrations')
            ->and($executedBindings[0])->toBe(['2024_01_01_000000_create_users_table']);
    });

    it('returns list of applied migrations', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturn([
                ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
                ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 1],
                ['name' => '2024_01_03_000000_add_user_email', 'batch' => 2],
            ]);

        $repository = new MigrationRepository();
        $applied = $repository->getApplied($connection);

        expect($applied)->toBe([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_add_user_email',
        ]);
    });

    it('returns list of applied migrations with batch numbers', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturn([
                ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
                ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 1],
                ['name' => '2024_01_03_000000_add_user_email', 'batch' => 2],
            ]);

        $repository = new MigrationRepository();
        $applied = $repository->getAppliedWithBatch($connection);

        expect($applied)->toBe([
            ['name' => '2024_01_01_000000_create_users_table', 'batch' => 1],
            ['name' => '2024_01_02_000000_create_posts_table', 'batch' => 1],
            ['name' => '2024_01_03_000000_add_user_email', 'batch' => 2],
        ]);
    });

    it('gets next batch number', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturn([
                ['max_batch' => 3],
            ]);

        $repository = new MigrationRepository();
        $nextBatch = $repository->getNextBatchNumber($connection);

        expect($nextBatch)->toBe(4);
    });

    it('returns batch 1 when no migrations exist', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturn([
                ['max_batch' => null],
            ]);

        $repository = new MigrationRepository();
        $nextBatch = $repository->getNextBatchNumber($connection);

        expect($nextBatch)->toBe(1);
    });

    it('gets migrations for last batch', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturnOnConsecutiveCalls(
                // First call: get max batch
                [['max_batch' => 2]],
                // Second call: get migrations for batch 2
                [
                    ['name' => '2024_01_03_000000_add_user_email'],
                    ['name' => '2024_01_04_000000_add_post_status'],
                ],
            );

        $repository = new MigrationRepository();
        $lastBatch = $repository->getLastBatchMigrations($connection);

        expect($lastBatch)->toBe([
            '2024_01_03_000000_add_user_email',
            '2024_01_04_000000_add_post_status',
        ]);
    });

    it('returns empty array when no migrations to rollback', function (): void {
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->method('query')
            ->willReturn([
                ['max_batch' => null],
            ]);

        $repository = new MigrationRepository();
        $lastBatch = $repository->getLastBatchMigrations($connection);

        expect($lastBatch)->toBe([]);
    });
});
