<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Exceptions\ConnectionException;
use PDO;
use RuntimeException;

function createTestDatabaseConfig(
    string $host = 'localhost',
    int $port = 3306,
    string $database = 'test',
    string $username = 'root',
    string $password = '',
    ?string $sslCa = null,
    bool $sslVerifyServerCert = false,
    ?string $sslCert = null,
    ?string $sslKey = null,
): DatabaseConfig {
    $tempDir = sys_get_temp_dir() . '/marko_mysql_test_' . uniqid();
    mkdir($tempDir . '/config', recursive: true);

    $configArray = [
        'driver' => 'mysql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
    ];

    if ($sslCa !== null) {
        $configArray['ssl_ca'] = $sslCa;
    }

    if ($sslVerifyServerCert) {
        $configArray['ssl_verify_server_cert'] = true;
    }

    if ($sslCert !== null) {
        $configArray['ssl_cert'] = $sslCert;
    }

    if ($sslKey !== null) {
        $configArray['ssl_key'] = $sslKey;
    }

    file_put_contents(
        $tempDir . '/config/database.php',
        '<?php return ' . var_export($configArray, true) . ';',
    );

    $paths = new ProjectPaths($tempDir);
    $config = new DatabaseConfig($paths);

    // Clean up temp files immediately (config is already loaded)
    unlink($tempDir . '/config/database.php');
    rmdir($tempDir . '/config');
    rmdir($tempDir);

    return $config;
}

describe('MySqlConnection', function (): void {
    it('implements ConnectionInterface', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new MySqlConnection($config);

        expect($connection)->toBeInstanceOf(ConnectionInterface::class);
    });

    it('constructs proper MySQL DSN from config', function (): void {
        $config = createTestDatabaseConfig(
            host: 'db.example.com',
            port: 3307,
            database: 'myapp',
        );
        $connection = new MySqlConnection($config);

        // We can verify DSN via a getter method for testing (uses default charset utf8mb4)
        expect($connection->getDsn())->toBe('mysql:host=db.example.com;port=3307;dbname=myapp;charset=utf8mb4');
    });

    it('connects lazily on first query', function (): void {
        // Connection with invalid host - should NOT throw on construction
        $config = createTestDatabaseConfig(host: 'nonexistent.invalid.host');
        $connection = new MySqlConnection($config);

        // Not connected yet - lazy connection, should only throw when we actually try to query
        expect($connection->isConnected())
            ->toBeFalse()
            ->and(fn () => $connection->query('SELECT 1'))->toThrow(ConnectionException::class);
    });

    it('sets PDO error mode to exceptions', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig();

        // Create a testable connection that captures PDO options
        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                // Return a mock PDO using SQLite in-memory for testing
                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::ATTR_ERRMODE])->toBe(PDO::ERRMODE_EXCEPTION);
    });

    it('sets charset from config', function (): void {
        $capturedDsn = '';
        $config = createTestDatabaseConfig();

        // Create a testable connection that captures the DSN
        $connection = new class ($config, $capturedDsn) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private string &$capturedDsn,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedDsn = $dsn;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedDsn)->toContain('charset=utf8mb4');
    });

    it('executes raw SQL queries with parameter binding', function (): void {
        $config = createTestDatabaseConfig();

        // Create a testable connection with SQLite for query testing
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                // Create a test table
                $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
                $pdo->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
                $pdo->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

                return $pdo;
            }
        };

        // Test query with bindings
        $results = $connection->query(
            'SELECT * FROM users WHERE name = ?',
            ['Alice'],
        );

        expect($results)
            ->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Alice')
            ->and($results[0]['email'])->toBe('alice@example.com');

        // Test execute (INSERT) with bindings
        $affected = $connection->execute(
            'INSERT INTO users (name, email) VALUES (?, ?)',
            ['Charlie', 'charlie@example.com'],
        );

        expect($affected)->toBe(1);

        // Verify the insert worked
        $results = $connection->query('SELECT * FROM users WHERE name = ?', ['Charlie']);
        expect($results)->toHaveCount(1);
    });

    it('prepares statements for repeated execution', function (): void {
        $config = createTestDatabaseConfig();

        // Create a testable connection with SQLite
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

                return $pdo;
            }
        };

        // Prepare a statement for repeated use
        $statement = $connection->prepare('INSERT INTO users (name) VALUES (?)');

        expect($statement)->toBeInstanceOf(StatementInterface::class);

        // Execute the prepared statement multiple times
        $statement->execute(['Alice']);
        $statement->execute(['Bob']);
        $statement->execute(['Charlie']);

        expect($statement->rowCount())->toBe(1);

        // Verify all inserts worked via a SELECT prepared statement
        $selectStmt = $connection->prepare('SELECT * FROM users');
        $selectStmt->execute();
        $results = $selectStmt->fetchAll();

        expect($results)->toHaveCount(3);
    });

    it('throws ConnectionException on connection failure with helpful message', function (): void {
        $config = createTestDatabaseConfig(
            host: 'nonexistent.invalid.host',
            database: 'testdb',
            username: 'baduser',
            password: 'badpass',
        );
        $connection = new MySqlConnection($config);

        try {
            $connection->connect();
            expect(true)->toBeFalse('Should have thrown ConnectionException');
        } catch (ConnectionException $e) {
            // Verify the exception has helpful information
            expect($e->getMessage())
                ->toContain('testdb')
                ->and($e->getMessage())->toContain('nonexistent.invalid.host')
                ->and($e->getMessage())->toContain('3306')
                ->and($e->getContext())->not->toBeEmpty()
                ->and($e->getSuggestion())->toContain('MySQL');
        }
    });

    it('properly disconnects and releases resources', function (): void {
        $config = createTestDatabaseConfig();

        // Create a testable connection with SQLite
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return new PDO('sqlite::memory:', options: $options);
            }
        };

        // Initially not connected
        expect($connection->isConnected())->toBeFalse();

        // Connect
        $connection->connect();
        expect($connection->isConnected())->toBeTrue();

        // Disconnect
        $connection->disconnect();
        expect($connection->isConnected())->toBeFalse();

        // Can reconnect after disconnect
        $connection->connect();
        expect($connection->isConnected())->toBeTrue();
    });

    it('implements TransactionInterface', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new MySqlConnection($config);

        expect($connection)->toBeInstanceOf(TransactionInterface::class);
    });

    it('implements beginTransaction() method', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return new PDO('sqlite::memory:', options: $options);
            }
        };

        $connection->connect();

        expect($connection->inTransaction())->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();
    });

    it('implements commit() method', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        $connection->beginTransaction();
        $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        $connection->commit();

        expect($connection->inTransaction())->toBeFalse();

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)
            ->toHaveCount(1)
            ->and($results[0]['value'])->toBe('test');
    });

    it('implements rollback() method', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        $connection->beginTransaction();
        $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        $connection->rollback();

        expect($connection->inTransaction())->toBeFalse();

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(0);
    });

    it('implements inTransaction() method returning boolean', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return new PDO('sqlite::memory:', options: $options);
            }
        };

        $connection->connect();

        $result = $connection->inTransaction();

        expect($result)
            ->toBeBool()
            ->and($result)->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();

        $connection->commit();

        expect($connection->inTransaction())->toBeFalse();
    });

    it('implements transaction(callable) method', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        $connection->transaction(function () use ($connection): void {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        });

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(1);
    });

    it('auto-commits when callback completes successfully', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        $connection->transaction(function () use ($connection): void {
            $connection->execute("INSERT INTO test_data (value) VALUES ('committed')");
        });

        // Verify not in transaction after callback
        expect($connection->inTransaction())->toBeFalse();

        // Verify data was committed
        $results = $connection->query('SELECT * FROM test_data');
        expect($results)
            ->toHaveCount(1)
            ->and($results[0]['value'])->toBe('committed');
    });

    it('auto-rolls back when callback throws exception', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        try {
            $connection->transaction(function () use ($connection): void {
                $connection->execute("INSERT INTO test_data (value) VALUES ('should_rollback')");
                throw new RuntimeException('Test exception');
            });
        } catch (RuntimeException) {
            // Expected
        }

        // Verify not in transaction after callback
        expect($connection->inTransaction())->toBeFalse();

        // Verify data was rolled back
        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(0);
    });

    it('re-throws exception after rollback', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        expect(function () use ($connection): void {
            $connection->transaction(function (): void {
                throw new RuntimeException('Original exception message');
            });
        })->toThrow(RuntimeException::class, 'Original exception message');
    });

    it('returns callback return value on success', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = new PDO('sqlite::memory:', options: $options);
                $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');

                return $pdo;
            }
        };

        $result = $connection->transaction(function () use ($connection): string {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");

            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('passes SSL CA cert in PDO options when configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig(sslCa: '/path/to/ca.pem');

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::MYSQL_ATTR_SSL_CA])->toBe('/path/to/ca.pem');
    });

    it('sets SSL verify server cert when configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig(sslCa: '/path/to/ca.pem', sslVerifyServerCert: true);

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::MYSQL_ATTR_SSL_CA])->toBe('/path/to/ca.pem')
            ->and($capturedOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT])->toBeTrue();
    });

    it('defaults SSL verify server cert to false when ssl_ca is set', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig(sslCa: '/path/to/ca.pem');

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT])->toBeFalse();
    });

    it('passes SSL client cert in PDO options when configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig(sslCert: '/path/to/client-cert.pem');

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::MYSQL_ATTR_SSL_CERT])->toBe('/path/to/client-cert.pem');
    });

    it('passes SSL client key in PDO options when configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig(sslKey: '/path/to/client-key.pem');

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::MYSQL_ATTR_SSL_KEY])->toBe('/path/to/client-key.pem');
    });

    it('omits SSL client cert and key from PDO options when not configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig();

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions)->not->toHaveKey(PDO::MYSQL_ATTR_SSL_CERT)
            ->and($capturedOptions)->not->toHaveKey(PDO::MYSQL_ATTR_SSL_KEY);
    });

    it('omits SSL CA cert from PDO options when not configured', function (): void {
        $capturedOptions = [];
        $config = createTestDatabaseConfig();

        $connection = new class ($config, $capturedOptions) extends MySqlConnection
        {
            public function __construct(
                DatabaseConfig $config,
                private array &$capturedOptions,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                return new PDO('sqlite::memory:');
            }
        };

        $connection->connect();

        expect($capturedOptions)->not->toHaveKey(PDO::MYSQL_ATTR_SSL_CA);
    });

    it('prevents nested transactions (throws exception)', function (): void {
        $config = createTestDatabaseConfig();
        $connection = new class ($config) extends MySqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return new PDO('sqlite::memory:', options: $options);
            }
        };

        $connection->beginTransaction();

        expect(fn () => $connection->beginTransaction())
            ->toThrow(TransactionException::class, 'Nested transactions are not supported');
    });
});
