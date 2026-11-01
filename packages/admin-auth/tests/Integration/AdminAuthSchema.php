<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Tests\Integration;

use Marko\AdminAuth\AdminUserProvider;
use Marko\AdminAuth\Entity\AdminUser;
use Marko\AdminAuth\Entity\AdminUserRole;
use Marko\AdminAuth\Entity\Permission;
use Marko\AdminAuth\Entity\RememberToken;
use Marko\AdminAuth\Entity\Role;
use Marko\AdminAuth\Entity\RolePermission;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\PermissionRepository;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Hashing\PasswordVerifier;
use Marko\Core\Command\ErrorOutput;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Command\MigrateCommand;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\ExpressionDefaultCanonicalizer;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Migration\DataMigrationDiscovery;
use Marko\Database\Migration\DataMigrator;
use Marko\Database\Migration\MigrationGenerator;
use Marko\Database\Migration\MigrationRepository;
use Marko\Database\Migration\Migrator;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfirmationPrompter;

/**
 * Builds the admin-auth tables on a real server for the PostgreSQL and MySQL/MariaDB integration tests, and the
 * repositories that use them.
 *
 * All six tables are created from their entities, the only source of the admin-auth schema: create() runs the
 * driver's generator over each entity in foreign-key order, and migrate() runs the real db:migrate command in a
 * temporary project that has marko/admin-auth installed, as a fresh install would.
 */
class AdminAuthSchema
{
    /** @var list<string> Tables in reverse dependency order, the order they are dropped in */
    public const array TABLES = [
        'remember_tokens',
        'admin_user_roles',
        'role_permissions',
        'admin_users',
        'roles',
        'permissions',
    ];

    /** @var list<class-string> Entities in foreign-key order, the order they are created in */
    private const array ENTITIES = [
        Role::class,
        Permission::class,
        RolePermission::class,
        AdminUser::class,
        AdminUserRole::class,
        RememberToken::class,
    ];

    public static function drop(
        ConnectionInterface $connection,
    ): void {
        foreach (self::TABLES as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $connection->quoteIdentifier($table));
        }
    }

    public static function create(
        ConnectionInterface $connection,
        SqlGeneratorInterface $generator,
    ): void {
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();

        foreach (self::ENTITIES as $entityClass) {
            $table = $schemaBuilder->build($metadataFactory->parse($entityClass));

            foreach ($generator->generateUp(new SchemaDiff(tablesToCreate: [$table->name => $table])) as $statement) {
                $connection->execute($statement);
            }
        }
    }

    /**
     * A temporary project with marko/admin-auth installed: vendor/marko/admin-auth links to this package, so
     * db:migrate discovers its entities in vendor/<vendor>/<package>/src/Entity exactly as in an application.
     */
    public static function project(): string
    {
        $project = sys_get_temp_dir() . '/marko-admin-auth-migrate-' . bin2hex(random_bytes(6));
        mkdir($project . '/vendor/marko', 0777, true);
        symlink(dirname(__DIR__, 2), $project . '/vendor/marko/admin-auth');

        return $project;
    }

    /**
     * Remove a project made by project(), without following the vendor link into the package.
     */
    public static function removeProject(
        string $project,
    ): void {
        unlink($project . '/vendor/marko/admin-auth');

        foreach (glob($project . '/database/migrations/*.php') ?: [] as $migration) {
            unlink($migration);
        }

        foreach (['/database/migrations', '/database', '/vendor/marko', '/vendor', ''] as $directory) {
            if (is_dir($project . $directory)) {
                rmdir($project . $directory);
            }
        }
    }

    /**
     * Run db:migrate in a project made by project(), in development so it generates and applies a migration for
     * any entity drift, as `marko db:migrate` does on a fresh install.
     *
     * @return array{exitCode: int, output: string}
     */
    public static function migrate(
        ConnectionInterface $connection,
        SqlGeneratorInterface $generator,
        IntrospectorInterface $introspector,
        string $project,
    ): array {
        $paths = new ProjectPaths($project);
        $migrations = new MigrationRepository();
        $scopedIntrospector = new AdminAuthTablesIntrospector($introspector);

        $command = new MigrateCommand(
            migrator: new Migrator($connection, $migrations, $paths),
            dataMigrator: new DataMigrator($connection, $migrations, new DataMigrationDiscovery($paths)),
            migrationGenerator: new MigrationGenerator($generator, $paths, new FakeClock()),
            entityDiscovery: new EntityDiscovery(new ClassFileParser()),
            introspector: $scopedIntrospector,
            schemaRegistry: new SchemaRegistry(new EntityMetadataFactory(), new SchemaBuilder()),
            diffCalculator: new DiffCalculator(),
            sqlGenerator: $generator,
            paths: $paths,
            appEnvironment: new AppEnvironment(['APP_ENV' => 'local']),
            confirmationPrompter: new FakeConfirmationPrompter(interactive: false),
            expressionDefaultCanonicalizer: new ExpressionDefaultCanonicalizer($introspector),
            errorOutput: new ErrorOutput(fopen('php://memory', 'r+')),
        );

        $stream = fopen('php://memory', 'r+');
        $exitCode = $command->execute(new Input(['marko', 'db:migrate']), new Output($stream));
        rewind($stream);

        return ['exitCode' => $exitCode, 'output' => (string) stream_get_contents($stream)];
    }

    /**
     * Drop the migrations table db:migrate records applied migrations in.
     */
    public static function dropMigrations(
        ConnectionInterface $connection,
    ): void {
        $connection->execute('DROP TABLE IF EXISTS ' . $connection->quoteIdentifier('migrations'));
    }

    public static function permissionRepository(
        ConnectionInterface $connection,
    ): PermissionRepository {
        $metadataFactory = new EntityMetadataFactory();

        return new PermissionRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory));
    }

    public static function roleRepository(
        ConnectionInterface $connection,
    ): RoleRepository {
        $metadataFactory = new EntityMetadataFactory();

        return new RoleRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory));
    }

    public static function adminUserRepository(
        ConnectionInterface $connection,
    ): AdminUserRepository {
        $metadataFactory = new EntityMetadataFactory();

        return new AdminUserRepository($connection, $metadataFactory, new EntityHydrator($metadataFactory));
    }

    public static function userProvider(
        ConnectionInterface $connection,
        PasswordHasherInterface $passwordHasher,
    ): AdminUserProvider {
        return new AdminUserProvider(
            userRepository: self::adminUserRepository($connection),
            roleRepository: self::roleRepository($connection),
            passwordHasher: $passwordHasher,
            passwordVerifier: new PasswordVerifier($passwordHasher),
        );
    }

    /**
     * Save a role and give it the permissions with the given keys.
     *
     * @param list<string> $permissionKeys
     */
    public static function roleWith(
        ConnectionInterface $connection,
        string $slug,
        array $permissionKeys,
    ): Role {
        $roleRepository = self::roleRepository($connection);
        $permissionRepository = self::permissionRepository($connection);

        $role = new Role();
        $role->name = ucfirst($slug);
        $role->slug = $slug;
        $roleRepository->save($role);

        $roleRepository->syncPermissions(
            (int) $role->id,
            array_map(
                fn (string $key): int => (int) $permissionRepository->findByKey($key)?->id,
                $permissionKeys,
            ),
        );

        return $role;
    }

    /**
     * Save a permission row directly, as a role-editing UI would for a wildcard grant.
     */
    public static function permission(
        ConnectionInterface $connection,
        string $key,
        string $label,
        string $group,
    ): void {
        $permission = new Permission();
        $permission->key = $key;
        $permission->label = $label;
        $permission->group = $group;

        self::permissionRepository($connection)->save($permission);
    }

    /**
     * Insert a permission row with raw SQL, bypassing the repository's key validation, as a row written before
     * keys had to be lowercase would be. Returns its id.
     */
    public static function legacyPermission(
        ConnectionInterface $connection,
        string $key,
        string $label,
        string $group,
    ): int {
        $connection->execute(
            sprintf(
                'INSERT INTO %s (%s, %s, %s) VALUES (?, ?, ?)',
                $connection->quoteIdentifier('permissions'),
                $connection->quoteIdentifier('key'),
                $connection->quoteIdentifier('label'),
                $connection->quoteIdentifier('group'),
            ),
            [$key, $label, $group],
        );

        $rows = $connection->query(
            sprintf(
                'SELECT %s FROM %s WHERE %s = ?',
                $connection->quoteIdentifier('id'),
                $connection->quoteIdentifier('permissions'),
                $connection->quoteIdentifier('key'),
            ),
            [$key],
        );

        return (int) $rows[0]['id'];
    }

    /**
     * The number of rows in a table.
     */
    public static function rowCount(
        ConnectionInterface $connection,
        string $table,
    ): int {
        $rows = $connection->query('SELECT COUNT(*) AS row_count FROM ' . $connection->quoteIdentifier($table));

        return (int) $rows[0]['row_count'];
    }

    /**
     * The number of role_permissions rows pointing at a permission id.
     */
    public static function assignmentCount(
        ConnectionInterface $connection,
        int $permissionId,
    ): int {
        return self::pivotCount($connection, 'role_permissions', 'permission_id', $permissionId);
    }

    /**
     * The number of rows in a pivot table whose column holds the given id.
     */
    public static function pivotCount(
        ConnectionInterface $connection,
        string $table,
        string $column,
        int $id,
    ): int {
        $rows = $connection->query(
            sprintf(
                'SELECT COUNT(*) AS assignments FROM %s WHERE %s = ?',
                $connection->quoteIdentifier($table),
                $connection->quoteIdentifier($column),
            ),
            [$id],
        );

        return (int) $rows[0]['assignments'];
    }

    /**
     * Save an admin user with the given roles.
     *
     * @param list<int> $roleIds
     */
    public static function userWith(
        ConnectionInterface $connection,
        string $email,
        array $roleIds,
    ): AdminUser {
        $user = new AdminUser();
        $user->email = $email;
        $user->password = 'hash';
        $user->name = $email;

        $users = self::adminUserRepository($connection);
        $users->save($user);
        $users->syncRoles((int) $user->id, $roleIds);

        return $user;
    }
}
