<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionalDdlInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Exceptions\ConnectionException;
use PDO;
use PDOException;
use RuntimeException;

function createTestPgSqlConfig(
    string $host = 'localhost',
    int $port = 5432,
    string $database = 'test',
    string $username = 'user',
    string $password = 'pass',
    ?string $sslmode = null,
    ?string $sslCa = null,
    ?string $sslCert = null,
    ?string $sslKey = null,
    ?string $timezone = null,
): DatabaseConfig {
    $tempDir = sys_get_temp_dir() . '/marko_pgsql_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/config', recursive: true);

    $configArray = [
        'driver' => 'pgsql',
        'host' => $host,
        'port' => $port,
        'database' => $database,
        'username' => $username,
        'password' => $password,
    ];

    if ($sslmode !== null) {
        $configArray['sslmode'] = $sslmode;
    }

    if ($sslCa !== null) {
        $configArray['ssl_ca'] = $sslCa;
    }

    if ($sslCert !== null) {
        $configArray['ssl_cert'] = $sslCert;
    }

    if ($sslKey !== null) {
        $configArray['ssl_key'] = $sslKey;
    }

    if ($timezone !== null) {
        $configArray['timezone'] = $timezone;
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

/**
 * Create a mock PDO that ignores the session SET statements for PostgreSQL tests.
 */
function createSqliteMockPdo(
    array $options = [],
): PDO {
    return new class ($options) extends PDO
    {
        /** @param array<int, mixed> $options */
        public function __construct(
            array $options,
        ) {
            parent::__construct('sqlite::memory:', options: $options);
        }

        public function exec(
            string $statement,
        ): int|false {
            // Skip the SET NAMES and SET TIME ZONE session statements (not supported in SQLite)
            if (str_starts_with($statement, 'SET ')) {
                return 0;
            }

            return parent::exec($statement);
        }
    };
}

/**
 * A SQLite-backed PDO that records the statements passed to exec() and ignores the session SET statements.
 *
 * @param list<string> $statements
 */
function createStatementRecordingPdo(
    array &$statements,
): PDO {
    return new class ($statements) extends PDO
    {
        /** @param list<string> $statements */
        public function __construct(
            private array &$statements,
        ) {
            parent::__construct('sqlite::memory:');
        }

        public function exec(
            string $statement,
        ): int|false {
            $this->statements[] = $statement;

            return str_starts_with($statement, 'SET ') ? 0 : parent::exec($statement);
        }
    };
}

/**
 * A PDO whose server rejects the SET TIME ZONE statement the way PostgreSQL rejects an unknown zone.
 */
function createTimezoneRejectingPdo(): PDO
{
    return new class () extends PDO
    {
        public function __construct()
        {
            parent::__construct('sqlite::memory:');
        }

        public function exec(
            string $statement,
        ): int|false {
            if (!str_starts_with($statement, 'SET TIME ZONE')) {
                return 0;
            }

            $exception = new PDOException(
                'SQLSTATE[22023]: Invalid parameter value: 7 ERROR:  invalid value for parameter "TimeZone": "America/New_York"',
            );
            $exception->errorInfo = ['22023', 7, 'ERROR:  invalid value for parameter "TimeZone": "America/New_York"'];

            throw $exception;
        }
    };
}

/**
 * Connect a PgSqlConnection and return the statements it ran on connect.
 *
 * @return list<string>
 */
function connectAndCaptureSessionStatements(
    DatabaseConfig $config,
): array {
    $statements = [];
    $connection = new class ($config, $statements) extends PgSqlConnection
    {
        /** @param list<string> $statements */
        public function __construct(
            DatabaseConfig $config,
            private array &$statements,
        ) {
            parent::__construct($config);
        }

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            return createStatementRecordingPdo($this->statements);
        }
    };

    $connection->connect();

    return $statements;
}

describe('PgSqlConnection', function (): void {
    it('implements ConnectionInterface', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new PgSqlConnection($config);

        expect($connection)->toBeInstanceOf(ConnectionInterface::class);
    });

    it('reports that PostgreSQL connections support RETURNING', function (): void {
        $connection = new PgSqlConnection(createTestPgSqlConfig());

        expect($connection->supportsReturning())->toBeTrue()
            ->and($connection->isConnected())->toBeFalse();
    });

    it('reports transactional DDL support on the PostgreSQL connection', function (): void {
        $connection = new PgSqlConnection(createTestPgSqlConfig());

        expect($connection)->toBeInstanceOf(TransactionalDdlInterface::class)
            ->and($connection->supportsTransactionalDdl())->toBeTrue()
            ->and($connection->isConnected())->toBeFalse();
    });

    it('quotes identifiers with double quotes without connecting', function (): void {
        $connection = new PgSqlConnection(createTestPgSqlConfig());

        expect($connection->quoteIdentifier('group'))->toBe('"group"')
            ->and($connection->quoteIdentifier('permissions.createdAt'))->toBe('"permissions"."createdAt"')
            ->and($connection->quoteIdentifier('we"ird'))->toBe('"we""ird"')
            ->and($connection->isConnected())->toBeFalse();
    });

    it('constructs proper PostgreSQL DSN from config', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5433,
            database: 'myapp',
        );
        $connection = new PgSqlConnection($config);

        // Use public getDsn() method instead of reflection
        expect($connection->getDsn())->toBe('pgsql:host=db.example.com;port=5433;dbname=myapp');
    });

    it('connects lazily on first query', function (): void {
        // Connection with invalid host - should NOT throw on construction
        $config = createTestPgSqlConfig(host: 'nonexistent.invalid.host');
        $connection = new PgSqlConnection($config);

        // Not connected yet - lazy connection, should only throw when we actually try to query
        expect($connection->isConnected())
            ->toBeFalse()
            ->and(fn () => $connection->query('SELECT 1'))->toThrow(ConnectionException::class);
    });

    it('sets PDO error mode to exceptions', function (): void {
        $capturedOptions = [];
        $config = createTestPgSqlConfig();

        // Create a testable connection that captures PDO options
        $connection = new class ($config, $capturedOptions) extends PgSqlConnection
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
                return createSqliteMockPdo($options);
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::ATTR_ERRMODE])->toBe(PDO::ERRMODE_EXCEPTION);
    });

    it('sets client encoding from config', function (): void {
        $capturedDsn = '';
        $config = createTestPgSqlConfig();

        // Create a testable connection that captures the DSN
        $connection = new class ($config, $capturedDsn) extends PgSqlConnection
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

                return createSqliteMockPdo($options);
            }
        };

        $connection->connect();

        // Verify DSN is correct (charset is set via SET NAMES query, not in DSN for PostgreSQL)
        expect($capturedDsn)->toBe('pgsql:host=localhost;port=5432;dbname=test');
    });

    it('executes raw SQL queries with parameter binding', function (): void {
        $config = createTestPgSqlConfig();

        // Create a testable connection with SQLite for query testing
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();

        // Create a testable connection with SQLite
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig(
            host: 'nonexistent.invalid.host',
            database: 'testdb',
            username: 'baduser',
            password: 'badpass',
        );
        $connection = new PgSqlConnection($config);

        try {
            $connection->connect();
            expect(true)->toBeFalse('Should have thrown ConnectionException');
        } catch (ConnectionException $e) {
            // Verify the exception has helpful information
            expect($e->getMessage())
                ->toContain('testdb')
                ->and($e->getMessage())->toContain('nonexistent.invalid.host')
                ->and($e->getMessage())->toContain('5432')
                ->and($e->getContext())->not->toBeEmpty();
        }
    });

    it('properly disconnects and releases resources', function (): void {
        $config = createTestPgSqlConfig();

        // Create a testable connection with SQLite
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new PgSqlConnection($config);

        expect($connection)->toBeInstanceOf(TransactionInterface::class);
    });

    it('implements beginTransaction() method', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return createSqliteMockPdo($options);
            }
        };

        $connection->connect();

        expect($connection->inTransaction())->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();
    });

    it('implements commit() method', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
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

    it('includes sslmode in DSN when configured', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5432,
            database: 'myapp',
            sslmode: 'require',
        );
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->toBe('pgsql:host=db.example.com;port=5432;dbname=myapp;sslmode=require');
    });

    it('passes sslmode verify-full and the CA through to the DSN', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5432,
            database: 'myapp',
            sslmode: 'verify-full',
            sslCa: '/path/to/ca.pem',
        );
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->toBe(
            'pgsql:host=db.example.com;port=5432;dbname=myapp;sslmode=verify-full;sslrootcert=/path/to/ca.pem',
        );
    });

    it('omits sslmode from DSN when not configured', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5432,
            database: 'myapp',
        );
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->not->toContain('sslmode');
    });

    it('includes sslrootcert in DSN when configured', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5432,
            database: 'myapp',
            sslCa: '/path/to/ca.pem',
        );
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->toContain('sslrootcert=/path/to/ca.pem');
    });

    it('omits sslrootcert from DSN when not configured', function (): void {
        $config = createTestPgSqlConfig(
            host: 'db.example.com',
            port: 5432,
            database: 'myapp',
        );
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->not->toContain('sslrootcert');
    });

    it('includes sslcert in DSN when configured', function (): void {
        $config = createTestPgSqlConfig(sslCert: '/path/to/client-cert.pem', sslKey: '/path/to/client-key.pem');
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->toContain('sslcert=/path/to/client-cert.pem');
    });

    it('omits sslcert from DSN when not configured', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->not->toContain('sslcert');
    });

    it('includes sslkey in DSN when configured', function (): void {
        $config = createTestPgSqlConfig(sslCert: '/path/to/client-cert.pem', sslKey: '/path/to/client-key.pem');
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->toContain('sslkey=/path/to/client-key.pem');
    });

    it('omits sslkey from DSN when not configured', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new PgSqlConnection($config);

        expect($connection->getDsn())->not->toContain('sslkey');
    });

    it('JSON-encodes array bindings instead of casting them to the string "Array"', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
                $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, metadata TEXT)');

                return $pdo;
            }
        };

        $connection->execute(
            'INSERT INTO items (metadata) VALUES (?)',
            [['key' => 'value', 'nested' => [1, 2, 3]]],
        );

        $rows = $connection->query('SELECT metadata FROM items');

        expect($rows[0]['metadata'])
            ->toBe('{"key":"value","nested":[1,2,3]}')
            ->not->toBe('Array');
    });

    it('throws ConnectionException when an array binding is not JSON-encodable', function (): void {
        $config = createTestPgSqlConfig();
        $connection = new class ($config) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $pdo = createSqliteMockPdo($options);
                $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, metadata TEXT)');

                return $pdo;
            }
        };

        expect(fn () => $connection->execute(
            'INSERT INTO items (metadata) VALUES (?)',
            [[NAN]],
        ))->toThrow(ConnectionException::class, "Failed to JSON-encode array bound to parameter '1'");
    });

    it('sets the session time zone to UTC on connect when the database timezone is UTC', function (): void {
        expect(connectAndCaptureSessionStatements(createTestPgSqlConfig()))
            ->toBe(["SET NAMES 'utf8'", "SET TIME ZONE 'UTC'"]);
    });

    it('sets the session time zone to the named zone on connect', function (): void {
        expect(connectAndCaptureSessionStatements(createTestPgSqlConfig(timezone: 'America/New_York')))
            ->toBe(["SET NAMES 'utf8'", "SET TIME ZONE 'America/New_York'"]);
    });

    it('sets a fixed offset as an ISO interval so PostgreSQL does not invert its sign', function (): void {
        expect(connectAndCaptureSessionStatements(createTestPgSqlConfig(timezone: '+05:30'))[1])
            ->toBe("SET TIME ZONE INTERVAL '+05:30' HOUR TO MINUTE")
            ->and(connectAndCaptureSessionStatements(createTestPgSqlConfig(timezone: 'CEST'))[1])
            ->toBe("SET TIME ZONE INTERVAL '+02:00' HOUR TO MINUTE")
            ->and(connectAndCaptureSessionStatements(createTestPgSqlConfig(timezone: 'utc'))[1])
            ->toBe("SET TIME ZONE 'UTC'");
    });

    it('sets the session time zone again after a reconnect', function (): void {
        $statements = [];
        $connection = new class (createTestPgSqlConfig(timezone: 'Europe/Paris'), $statements) extends PgSqlConnection
        {
            /** @param list<string> $statements */
            public function __construct(
                DatabaseConfig $config,
                private array &$statements,
            ) {
                parent::__construct($config);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return createStatementRecordingPdo($this->statements);
            }
        };

        $connection->connect();
        $connection->disconnect();
        $connection->connect();

        expect(array_values(array_filter(
            $statements,
            static fn (string $statement): bool => str_starts_with($statement, 'SET TIME ZONE'),
        )))->toBe(["SET TIME ZONE 'Europe/Paris'", "SET TIME ZONE 'Europe/Paris'"]);
    });

    it('throws ConnectionException naming the zone when the server rejects the time zone', function (): void {
        $connection = new class (createTestPgSqlConfig(timezone: 'America/New_York')) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                return createTimezoneRejectingPdo();
            }
        };

        try {
            $connection->connect();
            expect(true)->toBeFalse('Should have thrown ConnectionException');
        } catch (ConnectionException $e) {
            expect($e->getMessage())->toContain("'America/New_York'")
                ->and($e->getSuggestion())->toContain('pg_timezone_names')
                ->and($e->getPrevious())->toBeInstanceOf(PDOException::class);
        }
    });

    it('stays disconnected after a failed session setup so the next connect retries it', function (): void {
        $connection = new class (createTestPgSqlConfig(timezone: 'America/New_York')) extends PgSqlConnection
        {
            public int $pdoCount = 0;

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->pdoCount++;

                return createTimezoneRejectingPdo();
            }
        };

        expect(fn () => $connection->connect())->toThrow(ConnectionException::class)
            ->and($connection->isConnected())->toBeFalse()
            ->and(fn () => $connection->connect())->toThrow(ConnectionException::class)
            ->and($connection->pdoCount)->toBe(2);
    });
});
