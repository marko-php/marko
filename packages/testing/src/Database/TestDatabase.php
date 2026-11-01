<?php

declare(strict_types=1);

namespace Marko\Testing\Database;

use Marko\Core\Application;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\Migration\Migrator;
use Marko\Database\Testing\DatabaseTestHelper;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Testing\BootedApplications;
use Marko\Testing\Exceptions\DatabaseTestException;
use Marko\Testing\Http\TestClient;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use RuntimeException;

/**
 * A booted, migrated application for database tests, shared by every test in
 * the process.
 *
 * boot() boots the application at a base path once per process (the same
 * Application::boot() that TestClient::boot() uses) and runs the committed
 * migrations once with Migrator::migrate(). Later calls for the same path
 * return the same instance, so a suite pays for boot and migrations once,
 * not once per test. Pair it with RefreshDatabase (a rolled-back transaction
 * per test) or TruncateDatabase (empty the entity tables).
 *
 * It refuses to run in production (an unset APP_ENV counts as production),
 * and throws when marko/database or a driver is missing. Operations that
 * delete data (fresh: true, TruncateDatabase) run only in a testing
 * environment (testing, test).
 */
class TestDatabase
{
    /**
     * The interface whose existence proves marko/database is installed.
     */
    protected const string CONNECTION_INTERFACE = ConnectionInterface::class;

    /** @var array<string, array{database: TestDatabase, fresh: bool}> */
    private static array $booted = [];

    private ?DatabaseTestHelper $helper = null;

    /**
     * Wrap an application that is already booted and migrated. Prefer boot(),
     * which also checks the environment and the driver and runs the migrations.
     *
     * @param list<string> $appliedMigrations
     */
    public function __construct(
        private readonly Application $application,
        private readonly array $appliedMigrations = [],
    ) {}

    /**
     * Boot and migrate the application at $basePath, once per process.
     *
     * With $fresh, every migration is rolled back and re-run on the first boot
     * (db:rebuild), which deletes all data; this runs only in a testing
     * environment (testing, test).
     *
     * @throws DatabaseTestException|MigrationException|ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    public static function boot(
        string $basePath,
        bool $fresh = false,
    ): self {
        if (!interface_exists(static::CONNECTION_INTERFACE)) {
            throw DatabaseTestException::databasePackageMissing(static::CONNECTION_INTERFACE);
        }

        $key = realpath($basePath) ?: $basePath;

        if (isset(self::$booted[$key])) {
            $database = self::$booted[$key]['database'];
            $database->assertNotProduction();

            if (self::$booted[$key]['fresh'] !== $fresh) {
                throw DatabaseTestException::conflictingFresh($key, $fresh);
            }

            return $database;
        }

        // Booting reads the project's .env, so the environment is checked
        // after boot and before anything touches the database.
        $application = Application::boot($basePath);
        // Shut down after this test, even when one of the checks below refuses it. The
        // application stays shared and usable for later tests; shutting it down only removes
        // the global error and exception handlers its boot installed.
        BootedApplications::track($application);
        $booting = new self($application);
        $booting->assertNotProduction();

        if ($fresh) {
            $booting->assertDisposable('rebuild the database (fresh: true)');
        }

        $container = $application->container;

        if (!$container->has(ConnectionInterface::class) || !$container->has(TransactionInterface::class)) {
            throw DatabaseTestException::noDriver($key);
        }

        $migrator = $container->get(Migrator::class);

        if ($fresh) {
            $migrator->reset();
        }

        $database = new self($application, $migrator->migrate());
        self::$booted[$key] = ['database' => $database, 'fresh' => $fresh];

        return $database;
    }

    public function application(): Application
    {
        return $this->application;
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function connection(): ConnectionInterface
    {
        return $this->application->container->get(ConnectionInterface::class);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function transaction(): TransactionInterface
    {
        return $this->application->container->get(TransactionInterface::class);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function environment(): AppEnvironment
    {
        return $this->application->container->get(AppEnvironment::class);
    }

    /**
     * The migrations boot() applied in this process (empty when the schema was
     * already up to date).
     *
     * @return list<string>
     */
    public function appliedMigrations(): array
    {
        return $this->appliedMigrations;
    }

    /**
     * A new HTTP test client on this application. The client resets
     * request-scoped state before each request as usual, but leaves the
     * database connection alone: resetting it would roll back the test
     * transaction. Create one client per test.
     *
     * @throws ContainerExceptionInterface
     */
    public function client(): TestClient
    {
        $connections = array_filter(
            [$this->connection(), $this->transaction()],
            static fn (object $service): bool => $service instanceof ResettableInterface,
        );

        return TestClient::forApplication($this->application)->withoutResetting(...$connections);
    }

    /**
     * Insert rows into a table with plain INSERT statements.
     *
     * @param array<array<string, mixed>> $rows
     * @throws ContainerExceptionInterface|TransactionException
     */
    public function seedTable(
        string $tableName,
        array $rows,
    ): void {
        $this->helper()->seedTable($tableName, $rows);
    }

    /**
     * @throws ContainerExceptionInterface|TransactionException
     */
    public function getTableRowCount(
        string $tableName,
    ): int {
        return $this->helper()->getTableRowCount($tableName);
    }

    /**
     * @throws DatabaseTestException|ContainerExceptionInterface
     */
    public function assertNotProduction(): void
    {
        $environment = $this->environment();

        if ($environment->isProduction()) {
            throw DatabaseTestException::productionEnvironment($environment->name());
        }
    }

    /**
     * Refuse an operation that deletes data unless the application runs in a
     * testing environment (AppEnvironment::isTesting(): testing, test).
     * Production, development, staging and any other name are refused: only a
     * testing environment shows that the database is disposable.
     *
     * @throws DatabaseTestException|ContainerExceptionInterface
     */
    public function assertDisposable(
        string $operation,
    ): void {
        $environment = $this->environment();

        if (!$environment->isTesting()) {
            throw DatabaseTestException::destructiveInEnvironment($operation, $environment->name());
        }
    }

    /**
     * @throws ContainerExceptionInterface|TransactionException
     */
    private function helper(): DatabaseTestHelper
    {
        if ($this->helper !== null) {
            return $this->helper;
        }

        $connection = $this->connection();

        if (!$connection instanceof TransactionInterface) {
            throw TransactionException::connectionDoesNotSupportTransactions($connection::class);
        }

        return $this->helper = new DatabaseTestHelper($connection);
    }
}
