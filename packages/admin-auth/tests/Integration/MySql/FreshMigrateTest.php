<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Integration\MySql;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\PermissionRegistry;
use Marko\AdminAuth\Tests\Integration\AdminAuthSchema;
use Marko\AdminAuth\Tests\Integration\NativePasswordHasher;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Introspection\MySqlIntrospector;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\MySql\Tests\Fixtures\IntegrationDatabase;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;

/*
 * A fresh install of marko/admin-auth on a real MySQL server; CI also runs this directory against MariaDB 11.8 and
 * 10.11. db:migrate runs in a temporary project whose vendor/marko/admin-auth links to this package, so the tables
 * come from the entities exactly as in an application (#336: admin_user_roles had no entity and was never created).
 * Set MARKO_TEST_MYSQL_HOST (and optionally _PORT, _DATABASE, _USERNAME, _PASSWORD) to enable; the tests skip
 * otherwise. The tests create and drop the admin-auth tables and the migrations table.
 *
 * Settings come from the database-mysql IntegrationDatabase fixture. With MARKO_INTEGRATION_REQUIRED set (CI), a
 * missing host fails instead of skipping. Part of the integration-services group.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new MySqlConnection($config);
    $this->introspector = new MySqlIntrospector($this->connection, $config->database);
    AdminAuthSchema::drop($this->connection);
    AdminAuthSchema::dropMigrations($this->connection);
    $this->project = AdminAuthSchema::project();

    $this->migrate = fn (bool $force = false): array => AdminAuthSchema::migrate(
        $this->connection,
        new MySqlGenerator(),
        $this->introspector,
        $this->project,
        $force,
    );
});

afterEach(function (): void {
    if (isset($this->connection)) {
        AdminAuthSchema::drop($this->connection);
        AdminAuthSchema::dropMigrations($this->connection);
        AdminAuthSchema::removeProject($this->project);
        $this->connection->disconnect();
    }
});

/**
 * The tables the earlier hand-written migrations created, with their old idx_* index names, one row each.
 */
function legacyAdminAuthTables(
    MySqlConnection $connection,
): void {
    $legacy = [
        'CREATE TABLE roles (id INT UNSIGNED NOT NULL AUTO_INCREMENT, name VARCHAR(255) NOT NULL, '
        . 'slug VARCHAR(255) NOT NULL, description TEXT NULL, is_super_admin TINYINT(1) NOT NULL DEFAULT 0, '
        . 'created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, PRIMARY KEY (id), '
        . 'UNIQUE INDEX idx_roles_slug (slug))',
        'CREATE TABLE permissions (id INT UNSIGNED NOT NULL AUTO_INCREMENT, `key` VARCHAR(255) NOT NULL, '
        . 'label VARCHAR(255) NOT NULL, `group` VARCHAR(255) NOT NULL, created_at TIMESTAMP NULL, '
        . 'PRIMARY KEY (id), UNIQUE INDEX idx_permissions_key (`key`), INDEX idx_permissions_group (`group`))',
        'CREATE TABLE role_permissions (role_id INT UNSIGNED NOT NULL, permission_id INT UNSIGNED NOT NULL, '
        . 'UNIQUE INDEX idx_role_permissions_unique (role_id, permission_id), '
        . 'INDEX idx_role_permissions_permission_id (permission_id), '
        . 'FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, '
        . 'FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE)',
        'CREATE TABLE admin_users (id INT UNSIGNED NOT NULL AUTO_INCREMENT, email VARCHAR(255) NOT NULL, '
        . 'password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, remember_token VARCHAR(255) NULL, '
        . 'is_active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, '
        . 'PRIMARY KEY (id), UNIQUE INDEX idx_admin_users_email (email))',
        'CREATE TABLE admin_user_roles (user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL, '
        . 'UNIQUE INDEX idx_admin_user_roles_unique (user_id, role_id), '
        . 'INDEX idx_admin_user_roles_role_id (role_id), '
        . 'FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE, '
        . 'FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE)',
        "INSERT INTO roles (name, slug) VALUES ('Editor', 'editor')",
        "INSERT INTO permissions (`key`, label, `group`) VALUES ('blog.view', 'View', 'blog')",
        'INSERT INTO role_permissions (role_id, permission_id) VALUES (1, 1)',
        "INSERT INTO admin_users (email, password, name) VALUES ('a@example.com', 'x', 'A')",
        'INSERT INTO admin_user_roles (user_id, role_id) VALUES (1, 1)',
    ];

    foreach ($legacy as $sql) {
        $connection->execute($sql);
    }
}

describe('db:migrate for marko/admin-auth on MySQL', function (): void {
    it('creates every admin-auth table from the entities on a fresh db:migrate', function (): void {
        $result = ($this->migrate)();
        $missing = array_filter(
            AdminAuthSchema::TABLES,
            fn (string $table): bool => !$this->introspector->tableExists($table),
        );

        $uniqueIndexColumns = fn (string $table, string $name): ?array => array_find(
            $this->introspector->getIndexes($table),
            fn (Index $index): bool => $index->name === $name
                && $index->type === IndexType::Unique,
        )?->columns;

        expect($result['exitCode'])->toBe(0)
            ->and($result['output'])->toContain('Applied 6 schema migration(s).')
            ->and($missing)->toBeEmpty()
            ->and($uniqueIndexColumns('role_permissions', 'role_permissions_role_id_permission_id_unique'))
            ->toBe(['role_id', 'permission_id'])
            ->and($uniqueIndexColumns('admin_user_roles', 'admin_user_roles_user_id_role_id_unique'))
            ->toBe(['user_id', 'role_id']);
    });

    it('upgrades the tables of the earlier hand-written migrations, adding the pivot id keys', function (): void {
        legacyAdminAuthTables($this->connection);

        $result = ($this->migrate)(true);
        $second = ($this->migrate)();
        $pivotKey = fn (string $table): array => array_values(array_map(
            fn ($column): string => $column->name,
            array_filter($this->introspector->getTable($table)->columns, fn ($column): bool => $column->primaryKey),
        ));

        expect($result['exitCode'])->toBe(0)
            ->and($result['output'])->toContain('Applied 6 schema migration(s).')
            ->and($pivotKey('role_permissions'))->toBe(['id'])
            ->and($pivotKey('admin_user_roles'))->toBe(['id'])
            ->and($this->connection->query('SELECT id, role_id, permission_id FROM role_permissions'))
            ->toEqual([['id' => 1, 'role_id' => 1, 'permission_id' => 1]])
            ->and($this->connection->query('SELECT id, user_id, role_id FROM admin_user_roles'))
            ->toEqual([['id' => 1, 'user_id' => 1, 'role_id' => 1]])
            ->and($second['output'])->toContain('Nothing to migrate.');
    });

    it(
        'renames the legacy admin auth indexes to derived names on MySQL and then has nothing to migrate',
        function (): void {
            legacyAdminAuthTables($this->connection);

            $first = ($this->migrate)(true);
            $second = ($this->migrate)();
            $uniqueIndexColumns = fn (string $table, string $name): ?array => array_find(
                $this->introspector->getIndexes($table),
                fn (Index $index): bool => $index->name === $name
                    && $index->type === IndexType::Unique,
            )?->columns;

            expect($first['exitCode'])->toBe(0)
                ->and($uniqueIndexColumns('role_permissions', 'role_permissions_role_id_permission_id_unique'))
                ->toBe(['role_id', 'permission_id'])
                ->and($uniqueIndexColumns('admin_user_roles', 'admin_user_roles_user_id_role_id_unique'))
                ->toBe(['user_id', 'role_id'])
                ->and($second['exitCode'])->toBe(0)
                ->and($second['output'])->toContain('Nothing to migrate.');
        },
    );

    it('leaves no old copy of a renamed foreign key column index on MySQL', function (): void {
        legacyAdminAuthTables($this->connection);

        ($this->migrate)(true);

        $indexNames = fn (string $table): array => array_map(
            fn (Index $index): string => $index->name,
            $this->introspector->getIndexes($table),
        );

        expect($indexNames('role_permissions'))
            ->toContain('role_permissions_permission_id_index')
            ->not->toContain('idx_role_permissions_unique')
            ->not->toContain('idx_role_permissions_permission_id')
            ->and($indexNames('admin_user_roles'))
            ->toContain('admin_user_roles_role_id_index')
            ->not->toContain('idx_admin_user_roles_unique')
            ->not->toContain('idx_admin_user_roles_role_id');
    });

    it('reports nothing to migrate on a second run', function (): void {
        ($this->migrate)();

        $second = ($this->migrate)();

        expect($second['exitCode'])->toBe(0)
            ->and($second['output'])->toContain('Nothing to migrate.')
            ->and($second['output'])->not->toContain('Generated:');
    });

    it('logs in an admin user and loads roles and permissions', function (): void {
        ($this->migrate)();

        $registry = new PermissionRegistry();
        $registry->register('blog.posts.view', 'View Posts', 'blog');
        $registry->register('blog.posts.edit', 'Edit Posts', 'blog');
        AdminAuthSchema::permissionRepository($this->connection)->syncFromRegistry($registry);
        $role = AdminAuthSchema::roleWith($this->connection, 'editor', ['blog.posts.view', 'blog.posts.edit']);

        $hasher = new NativePasswordHasher();
        $user = new AdminUser();
        $user->email = 'editor@example.com';
        $user->password = $hasher->hash('secret');
        $user->name = 'Editor';
        $users = AdminAuthSchema::adminUserRepository($this->connection);
        $users->save($user);
        $users->syncRoles((int) $user->id, [(int) $role->id]);

        $provider = AdminAuthSchema::userProvider($this->connection, $hasher);
        $credentials = ['email' => 'editor@example.com', 'password' => 'secret'];
        $loggedIn = $provider->retrieveByCredentials($credentials);

        expect($loggedIn)->toBeInstanceOf(AdminUser::class)
            ->and($provider->validateCredentials($loggedIn, $credentials))->toBeTrue()
            ->and($loggedIn->hasRole('editor'))->toBeTrue()
            ->and($loggedIn->getPermissionKeys())->toEqualCanonicalizing(['blog.posts.view', 'blog.posts.edit']);
    });
});
