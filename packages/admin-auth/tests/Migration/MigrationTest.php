<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Migration\Migration;

/**
 * Helper to create a mock connection that captures executed SQL.
 *
 * @param array<string> $capturedSql
 */
function adminAuthMigrationCreateMockConnection(
    array &$capturedSql,
): ConnectionInterface {
    return new class ($capturedSql) implements ConnectionInterface
    {
        /**
         * @param array<string> $sql
         */
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$sql,
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
            $this->sql[] = $sql;

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
}

/**
 * Load a migration file and return the migration instance.
 */
function adminAuthMigrationLoadMigration(
    string $filename,
): Migration {
    $path = dirname(__DIR__, 2) . '/database/migrations/' . $filename;

    return require $path;
}

describe('AdminAuth Migrations', function (): void {
    it('creates migration for roles table with correct columns and indexes', function (): void {
        $capturedSql = [];
        $connection = adminAuthMigrationCreateMockConnection($capturedSql);
        $migration = adminAuthMigrationLoadMigration('001_create_roles_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('roles')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('name')
            ->and($sql)->toContain('slug')
            ->and($sql)->toContain('description')
            ->and($sql)->toContain('is_super_admin')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('updated_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('idx_roles_slug')
            ->and($sql)->toContain('DEFAULT 0')
            ->and($sql)->toContain('TEXT');
    });

    it('creates migration for permissions table with unique key column', function (): void {
        $capturedSql = [];
        $connection = adminAuthMigrationCreateMockConnection($capturedSql);
        $migration = adminAuthMigrationLoadMigration('002_create_permissions_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('permissions')
            ->and($sql)->toContain('id')
            ->and($sql)->toContain('`key`')
            ->and($sql)->toContain('label')
            ->and($sql)->toContain('`group`')
            ->and($sql)->toContain('created_at')
            ->and($sql)->toContain('PRIMARY KEY')
            ->and($sql)->toContain('AUTO_INCREMENT')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('idx_permissions_key');
    });

    it('creates migration for role_permissions junction table with unique constraint', function (): void {
        $capturedSql = [];
        $connection = adminAuthMigrationCreateMockConnection($capturedSql);
        $migration = adminAuthMigrationLoadMigration('003_create_role_permissions_table.php');

        $migration->up($connection);

        expect($capturedSql)->toHaveCount(1);

        $sql = $capturedSql[0];
        expect($sql)
            ->toContain('CREATE TABLE')
            ->and($sql)->toContain('role_permissions')
            ->and($sql)->toContain('role_id')
            ->and($sql)->toContain('permission_id')
            ->and($sql)->toContain('UNIQUE')
            ->and($sql)->toContain('idx_role_permissions_unique')
            ->and($sql)->toContain('FOREIGN KEY')
            ->and($sql)->toContain('REFERENCES roles')
            ->and($sql)->toContain('REFERENCES permissions')
            ->and($sql)->toContain('ON DELETE CASCADE');
    });

    it('adds indexes for frequently queried columns', function (): void {
        $indexExpectations = [
            '001_create_roles_table.php' => ['idx_roles_slug'],
            '002_create_permissions_table.php' => ['idx_permissions_key', 'idx_permissions_group'],
            '003_create_role_permissions_table.php' => ['idx_role_permissions_unique', 'idx_role_permissions_permission_id'],
        ];

        foreach ($indexExpectations as $filename => $expectedIndexes) {
            $capturedSql = [];
            $connection = adminAuthMigrationCreateMockConnection($capturedSql);
            $migration = adminAuthMigrationLoadMigration($filename);

            $migration->up($connection);

            $sql = $capturedSql[0];
            expect($sql)->toContain('INDEX');

            foreach ($expectedIndexes as $indexName) {
                expect($sql)->toContain($indexName);
            }
        }
    });

    it('can rollback all migrations cleanly', function (): void {
        $migrations = [
            '001_create_roles_table.php' => 'DROP TABLE roles',
            '002_create_permissions_table.php' => 'DROP TABLE permissions',
            '003_create_role_permissions_table.php' => 'DROP TABLE role_permissions',
        ];

        foreach ($migrations as $filename => $expectedSql) {
            $capturedSql = [];
            $connection = adminAuthMigrationCreateMockConnection($capturedSql);
            $migration = adminAuthMigrationLoadMigration($filename);

            $migration->down($connection);

            expect($capturedSql)
                ->toHaveCount(1)
                ->and($capturedSql[0])->toBe($expectedSql);
        }
    });
});
