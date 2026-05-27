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

function makePgSqlTestConnection(array &$queryCalls = [], array &$executeCalls = []): ConnectionInterface&TransactionInterface
{
    return new class ($queryCalls, $executeCalls) implements ConnectionInterface, TransactionInterface
    {
        public function __construct(
            private array &$queryCalls,
            private array &$executeCalls,
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
            $this->queryCalls[] = $sql;

            return [['result' => 'row']];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->executeCalls[] = $sql;

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
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

function makePgSqlConfigRepository(): ConfigRepositoryInterface
{
    return new class () implements ConfigRepositoryInterface
    {
        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'database.driver' => 'readwrite',
                'database.connections' => [
                    'write' => ['driver' => 'pgsql', 'host' => 'write-pg-host', 'port' => 5432, 'database' => 'mydb', 'username' => 'pguser', 'password' => 'secret'],
                    'read' => [
                        ['driver' => 'pgsql', 'host' => 'replica-pg-host', 'port' => 5432, 'database' => 'mydb', 'username' => 'pguser', 'password' => 'secret'],
                    ],
                    'read_strategy' => 'random',
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

function makePgSqlSpyFactory(): ConnectionFactoryInterface
{
    return new class () implements ConnectionFactoryInterface
    {
        /** @var array<string, array> */
        public array $writeCalls = [];

        /** @var array<string, array> */
        public array $replicaCalls = [];

        public ConnectionInterface&TransactionInterface $writeConnection;

        public ConnectionInterface&TransactionInterface $replicaConnection;

        public function __construct()
        {
            $this->writeConnection = makePgSqlTestConnection($this->writeCalls, $this->writeCalls);
            $this->replicaConnection = makePgSqlTestConnection($this->replicaCalls, $this->replicaCalls);
        }

        private int $callIndex = 0;

        public function make(DatabaseConfig $config): ConnectionInterface
        {
            $index = $this->callIndex++;

            return $index === 0 ? $this->writeConnection : $this->replicaConnection;
        }
    };
}

function makePgSqlContainer(ConfigRepositoryInterface $config, ConnectionFactoryInterface $factory): ContainerInterface
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

function bootPgSqlModule(ContainerInterface $container): void
{
    $module = require dirname(__DIR__, 2) . '/module.php';
    ($module['boot'])($container);
}

describe('pgsql wiring integration', function (): void {
    it('wires ReadWriteConnection as ConnectionInterface via boot callback with pgsql config', function (): void {
        $config = makePgSqlConfigRepository();
        $factory = makePgSqlSpyFactory();
        $container = makePgSqlContainer($config, $factory);

        bootPgSqlModule($container);

        expect($container->registered)->toHaveKey(ConnectionInterface::class)
            ->and($container->registered[ConnectionInterface::class])->toBeInstanceOf(ReadWriteConnection::class);
    });

    it('routes read queries to the replica connection', function (): void {
        $replicaQueries = [];
        $replicaConn = makePgSqlTestConnection($replicaQueries);

        $factory = new class ($replicaConn) implements ConnectionFactoryInterface
        {
            private int $callIndex = 0;

            public function __construct(
                private ConnectionInterface&TransactionInterface $replicaConnection,
            ) {}

            public function make(DatabaseConfig $config): ConnectionInterface
            {
                $index = $this->callIndex++;

                return $index === 0 ? makePgSqlTestConnection() : $this->replicaConnection;
            }
        };

        $config = makePgSqlConfigRepository();
        $container = makePgSqlContainer($config, $factory);
        bootPgSqlModule($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];
        $rwConn->query('SELECT 1');

        expect($replicaQueries)->toContain('SELECT 1');
    });

    it('routes write queries to the write connection', function (): void {
        $writeExecutes = [];
        $writeConn = makePgSqlTestConnection(executeCalls: $writeExecutes);

        $factory = new class ($writeConn) implements ConnectionFactoryInterface
        {
            private int $callIndex = 0;

            public function __construct(
                private ConnectionInterface&TransactionInterface $writeConnection,
            ) {}

            public function make(DatabaseConfig $config): ConnectionInterface
            {
                $index = $this->callIndex++;

                return $index === 0 ? $this->writeConnection : makePgSqlTestConnection();
            }
        };

        $config = makePgSqlConfigRepository();
        $container = makePgSqlContainer($config, $factory);
        bootPgSqlModule($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];
        $rwConn->execute('INSERT INTO users (name) VALUES (?)', ['Alice']);

        expect($writeExecutes)->toContain('INSERT INTO users (name) VALUES (?)');
    });

    it('activates sticky write after execute', function (): void {
        $writeQueries = [];
        $replicaQueries = [];
        $writeConn = makePgSqlTestConnection($writeQueries);
        $replicaConn = makePgSqlTestConnection($replicaQueries);

        $factory = new class ($writeConn, $replicaConn) implements ConnectionFactoryInterface
        {
            private int $callIndex = 0;

            public function __construct(
                private ConnectionInterface&TransactionInterface $writeConnection,
                private ConnectionInterface&TransactionInterface $replicaConnection,
            ) {}

            public function make(DatabaseConfig $config): ConnectionInterface
            {
                $index = $this->callIndex++;

                return $index === 0 ? $this->writeConnection : $this->replicaConnection;
            }
        };

        $config = makePgSqlConfigRepository();
        $container = makePgSqlContainer($config, $factory);
        bootPgSqlModule($container);

        /** @var ReadWriteConnection $rwConn */
        $rwConn = $container->registered[ConnectionInterface::class];

        // Execute a write — this should activate sticky write
        $rwConn->execute('INSERT INTO logs (msg) VALUES (?)', ['event']);

        // Subsequent query should go to write connection, not replica
        $rwConn->query('SELECT * FROM logs');

        expect($writeQueries)->toContain('SELECT * FROM logs')
            ->and($replicaQueries)->not->toContain('SELECT * FROM logs');
    });

    it('resolves TransactionInterface to the same ReadWriteConnection instance', function (): void {
        $config = makePgSqlConfigRepository();
        $factory = makePgSqlSpyFactory();
        $container = makePgSqlContainer($config, $factory);

        bootPgSqlModule($container);

        expect($container->registered)->toHaveKey(TransactionInterface::class)
            ->and($container->registered[TransactionInterface::class])
            ->toBe($container->registered[ConnectionInterface::class]);
    });
});
