<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Integration\PgSql;

use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\PermissionRegistry;
use Marko\AdminAuth\Tests\Integration\AdminAuthSchema;
use Marko\AdminAuth\Tests\Integration\NativePasswordHasher;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\PgSql\Tests\Fixtures\IntegrationDatabase;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;

/*
 * A fresh install of marko/admin-auth on a real PostgreSQL server. db:migrate runs in a temporary project whose
 * vendor/marko/admin-auth links to this package, so the tables come from the entities exactly as in an application
 * (#336: admin_user_roles had no entity and was never created).
 * Set MARKO_TEST_PGSQL_HOST (and optionally _PORT, _DATABASE, _USERNAME, _PASSWORD) to enable; the tests skip
 * otherwise. The tests create and drop the admin-auth tables and the migrations table.
 *
 * Settings come from the database-pgsql IntegrationDatabase fixture. With MARKO_INTEGRATION_REQUIRED set (CI), a
 * missing host fails instead of skipping. Part of the integration-services group.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $config = IntegrationDatabase::config();

    if ($config === null) {
        $this->markTestSkipped(IntegrationDatabase::SKIP_REASON);
    }

    $this->connection = new PgSqlConnection($config);
    $this->introspector = new PgSqlIntrospector($this->connection);
    AdminAuthSchema::drop($this->connection);
    AdminAuthSchema::dropMigrations($this->connection);
    $this->project = AdminAuthSchema::project();

    $this->migrate = fn (): array => AdminAuthSchema::migrate(
        $this->connection,
        new PgSqlGenerator(),
        $this->introspector,
        $this->project,
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

describe('db:migrate for marko/admin-auth on PostgreSQL', function (): void {
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
