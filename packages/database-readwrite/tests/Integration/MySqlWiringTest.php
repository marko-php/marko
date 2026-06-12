<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionFactoryInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;
use Marko\Database\ReadWrite\Replica\WeightedReplicaSelector;

function makeMySqlTestConnection(bool $failOnQuery = false): ConnectionInterface&TransactionInterface
{
    return new readonly class ($failOnQuery) implements ConnectionInterface, TransactionInterface
    {
        public function __construct(private bool $failOnQuery) {}

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
            if ($this->failOnQuery) {
                throw new PDOException('Connection refused');
            }

            return ['result' => 'mysql-read'];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }

        public function driverName(): string
        {
            return 'mysql';
        }

        public function beginTransaction(): void {}

        public function commit(): void {}

        public function rollback(): void {}

        public function inTransaction(): bool
        {
            return false;
        }

        public function transaction(callable $callback): mixed
        {
            return $callback();
        }
    };
}

function getMySqlBootCallback(): Closure
{
    $module = require dirname(__DIR__, 2) . '/module.php';

    return $module['boot'];
}

function makeMySqlConfigRepository(
    string $readStrategy = 'random',
    array $reads = [],
): ConfigRepositoryInterface {
    $defaultReads = [
        ['driver' => 'mysql', 'host' => 'read-host-1', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret'],
    ];

    $finalReads = $reads !== [] ? $reads : $defaultReads;

    return new readonly class ($readStrategy, $finalReads) implements ConfigRepositoryInterface
    {
        public function __construct(
            private string $readStrategy,
            private array $reads,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'database.driver' => 'readwrite',
                'database.connections' => [
                    'write' => ['driver' => 'mysql', 'host' => 'write-host', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret'],
                    'read' => $this->reads,
                    'read_strategy' => $this->readStrategy,
                ],
                default => null,
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return true;
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return '';
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return 0;
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return false;
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return 0.0;
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return [];
        }

        public function all(?string $scope = null): array
        {
            return [];
        }

        public function withScope(string $scope): ConfigRepositoryInterface
        {
            return $this;
        }
    };
}

function makeMySqlSpyFactory(bool $failFirstReplica = false): ConnectionFactoryInterface
{
    return new class ($failFirstReplica) implements ConnectionFactoryInterface
    {
        public int $callCount = 0;

        /** @var array<DatabaseConfig> */
        public array $receivedConfigs = [];

        /** @var array<ConnectionInterface> */
        public array $createdConnections = [];

        public function __construct(private bool $failFirstReplica) {}

        public function make(DatabaseConfig $config): ConnectionInterface
        {
            $this->callCount++;
            $this->receivedConfigs[] = $config;

            // First call is for write connection, subsequent calls are replicas
            $isWrite = $this->callCount === 1;
            $isFirstReplica = $this->callCount === 2;
            $shouldFail = !$isWrite && $isFirstReplica && $this->failFirstReplica;

            $conn = makeMySqlTestConnection(failOnQuery: $shouldFail);
            $this->createdConnections[] = $conn;

            return $conn;
        }
    };
}

function makeMySqlContainer(ConfigRepositoryInterface $config, ConnectionFactoryInterface $factory): ContainerInterface
{
    return new class ($config, $factory) implements ContainerInterface
    {
        /** @var array<string, object> */
        public array $registered = [];

        public function __construct(
            private ConfigRepositoryInterface $config,
            private ConnectionFactoryInterface $factory,
        ) {}

        public function get(string $id): mixed
        {
            return match ($id) {
                ConfigRepositoryInterface::class => $this->config,
                ConnectionFactoryInterface::class => $this->factory,
                default => throw new RuntimeException("Unexpected get($id)"),
            };
        }

        public function has(string $id): bool
        {
            return true;
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {
            $this->registered[$id] = $instance;
        }

        public function call(Closure $callable): mixed
        {
            return $callable($this);
        }
    };
}

describe('mysql wiring integration', function (): void {
    it('wires ReadWriteConnection as ConnectionInterface via boot callback with mysql config', function (): void {
        $config = makeMySqlConfigRepository();
        $factory = makeMySqlSpyFactory();
        $container = makeMySqlContainer($config, $factory);

        $boot = getMySqlBootCallback();
        $boot($container);

        expect($container->registered)->toHaveKey(ConnectionInterface::class)
            ->and($container->registered[ConnectionInterface::class])->toBeInstanceOf(ReadWriteConnection::class);
    });

    it('routes read queries to the replica connection with mysql driver', function (): void {
        $config = makeMySqlConfigRepository();
        $factory = makeMySqlSpyFactory();
        $container = makeMySqlContainer($config, $factory);

        $boot = getMySqlBootCallback();
        $boot($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];

        $result = $rwConn->query('SELECT 1');

        expect($result)->toBe(['result' => 'mysql-read']);
    });

    it('routes write queries to the write connection with mysql driver', function (): void {
        $config = makeMySqlConfigRepository();
        $factory = makeMySqlSpyFactory();
        $container = makeMySqlContainer($config, $factory);

        $boot = getMySqlBootCallback();
        $boot($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];

        $rowsAffected = $rwConn->execute('INSERT INTO users (name) VALUES (?)', ['Alice']);

        expect($rowsAffected)->toBe(1);
    });

    it('uses WeightedReplicaSelector when read_strategy is weighted with mysql config', function (): void {
        $config = makeMySqlConfigRepository(
            readStrategy: 'weighted',
            reads: [
                ['driver' => 'mysql', 'host' => 'read-host-1', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret', 'weight' => 1],
                ['driver' => 'mysql', 'host' => 'read-host-2', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret', 'weight' => 3],
            ],
        );
        $factory = makeMySqlSpyFactory();
        $container = makeMySqlContainer($config, $factory);

        $boot = getMySqlBootCallback();
        $boot($container);

        $rwConn = $container->registered[ConnectionInterface::class];

        $reflection = new ReflectionClass($rwConn);
        $selectorProp = $reflection->getProperty('replicaSelector');
        $selector = $selectorProp->getValue($rwConn);

        expect($selector)->toBeInstanceOf(WeightedReplicaSelector::class);
    });

    it('falls back to next replica when first replica fails', function (): void {
        $config = makeMySqlConfigRepository(
            reads: [
                ['driver' => 'mysql', 'host' => 'read-host-1', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret'],
                ['driver' => 'mysql', 'host' => 'read-host-2', 'port' => 3306, 'database' => 'app_db', 'username' => 'app_user', 'password' => 'secret'],
            ],
        );

        $failFirstReplica = true;
        $factory = makeMySqlSpyFactory(failFirstReplica: $failFirstReplica);
        $container = makeMySqlContainer($config, $factory);

        $boot = getMySqlBootCallback();
        $boot($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];

        // The first replica will throw a PDOException; the second should succeed
        $result = $rwConn->query('SELECT 1');

        expect($result)->toBe(['result' => 'mysql-read']);
    });
});
