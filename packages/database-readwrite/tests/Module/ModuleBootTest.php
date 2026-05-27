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
use Marko\Database\ReadWrite\Replica\RandomReplicaSelector;
use Marko\Database\ReadWrite\Replica\WeightedReplicaSelector;

function makeTestConnection(): ConnectionInterface&TransactionInterface
{
    return new class () implements ConnectionInterface, TransactionInterface
    {
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
            return 0;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
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

function getBootCallback(): Closure
{
    $module = require dirname(__DIR__, 2) . '/module.php';

    return $module['boot'];
}

function makeConfigRepository(
    string $driver = 'readwrite',
    string $readStrategy = 'random',
    array $extraReads = [],
): ConfigRepositoryInterface {
    $reads = array_merge(
        [['driver' => 'mysql', 'host' => 'read-host-1', 'port' => 3306, 'database' => 'db', 'username' => 'root', 'password' => '']],
        $extraReads,
    );

    return new class ($driver, $readStrategy, $reads) implements ConfigRepositoryInterface
    {
        public function __construct(
            private string $driver,
            private string $readStrategy,
            private array $reads,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'database.driver' => $this->driver,
                'database.connections' => [
                    'write' => ['driver' => 'mysql', 'host' => 'write-host', 'port' => 3306, 'database' => 'db', 'username' => 'root', 'password' => ''],
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

function makeTestContainer(ConfigRepositoryInterface $config, ConnectionFactoryInterface $factory): ContainerInterface
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

function makeSpyFactory(): ConnectionFactoryInterface
{
    return new class () implements ConnectionFactoryInterface
    {
        public int $callCount = 0;

        /** @var array<DatabaseConfig> */
        public array $receivedConfigs = [];

        /** @var array<ConnectionInterface> */
        public array $createdConnections = [];

        public function make(DatabaseConfig $config): ConnectionInterface
        {
            $this->callCount++;
            $this->receivedConfigs[] = $config;
            $conn = makeTestConnection();
            $this->createdConnections[] = $conn;

            return $conn;
        }
    };
}

describe('module boot callback', function (): void {
    it('does not activate when driver is not readwrite', function (): void {
        $config = makeConfigRepository(driver: 'mysql');
        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        expect($container->registered)->toBeEmpty();
    });

    it('registers ReadWriteConnection for ConnectionInterface when driver is readwrite', function (): void {
        $config = makeConfigRepository(driver: 'readwrite');
        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        expect($container->registered)->toHaveKey(ConnectionInterface::class)
            ->and($container->registered[ConnectionInterface::class])->toBeInstanceOf(ReadWriteConnection::class);
    });

    it('registers ReadWriteConnection for TransactionInterface when driver is readwrite', function (): void {
        $config = makeConfigRepository(driver: 'readwrite');
        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        expect($container->registered)->toHaveKey(TransactionInterface::class)
            ->and($container->registered[TransactionInterface::class])->toBeInstanceOf(ReadWriteConnection::class);
    });

    it('uses the factory to build write and replica connections', function (): void {
        $config = makeConfigRepository(driver: 'readwrite');
        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        expect($factory->callCount)->toBe(2);
    });

    it('uses RandomReplicaSelector when read_strategy is random', function (): void {
        $config = makeConfigRepository(driver: 'readwrite', readStrategy: 'random');
        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        $rwConn = $container->registered[ConnectionInterface::class];

        // Verify the connection is a ReadWriteConnection with a RandomReplicaSelector
        // by reflection inspection
        $reflection = new ReflectionClass($rwConn);
        $selectorProp = $reflection->getProperty('replicaSelector');
        $selector = $selectorProp->getValue($rwConn);

        expect($selector)->toBeInstanceOf(RandomReplicaSelector::class);
    });

    it('uses WeightedReplicaSelector when read_strategy is weighted', function (): void {
        // Each read needs a weight when using the weighted strategy
        $config = new class () implements ConfigRepositoryInterface
        {
            public function get(
                string $key,
                ?string $scope = null,
            ): mixed {
                return match ($key) {
                    'database.driver' => 'readwrite',
                    'database.connections' => [
                        'write' => ['driver' => 'mysql', 'host' => 'write-host', 'port' => 3306, 'database' => 'db', 'username' => 'root', 'password' => ''],
                        'read' => [
                            ['driver' => 'mysql', 'host' => 'read-host-1', 'port' => 3306, 'database' => 'db', 'username' => 'root', 'password' => '', 'weight' => 1],
                            ['driver' => 'mysql', 'host' => 'read-host-2', 'port' => 3306, 'database' => 'db', 'username' => 'root', 'password' => '', 'weight' => 2],
                        ],
                        'read_strategy' => 'weighted',
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

        $factory = makeSpyFactory();
        $container = makeTestContainer($config, $factory);

        $boot = getBootCallback();
        $boot($container);

        $rwConn = $container->registered[ConnectionInterface::class];

        $reflection = new ReflectionClass($rwConn);
        $selectorProp = $reflection->getProperty('replicaSelector');
        $selector = $selectorProp->getValue($rwConn);

        expect($selector)->toBeInstanceOf(WeightedReplicaSelector::class);
    });
});
