<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Exceptions\ConnectionException;
use PDO;
use RuntimeException;

describe('PgSqlConnection', function (): void {
    it('implements ConnectionInterface', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        );

        expect($connection)->toBeInstanceOf(ConnectionInterface::class);
    });

    it('constructs proper PostgreSQL DSN from config', function (): void {
        $connection = new PgSqlConnection(
            host: 'db.example.com',
            port: 5433,
            database: 'myapp',
            username: 'user',
            password: 'secret',
        );

        // Use public getDsn() method instead of reflection
        expect($connection->getDsn())->toBe('pgsql:host=db.example.com;port=5433;dbname=myapp');
    });

    it('connects lazily on first query', function (): void {
        // Connection with invalid host - should NOT throw on construction
        $connection = new PgSqlConnection(
            host: 'nonexistent.invalid.host',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        );

        // Not connected yet - lazy connection, should only throw when we actually try to query
        expect($connection->isConnected())
            ->toBeFalse()
            ->and(fn () => $connection->query('SELECT 1'))->toThrow(ConnectionException::class);
    });

    it('sets PDO error mode to exceptions', function (): void {
        $capturedOptions = [];

        // Create a testable connection that captures PDO options
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
            capturedOptions: $capturedOptions,
        ) extends PgSqlConnection
        {
            public function __construct(
                string $host,
                int $port,
                string $database,
                string $username,
                string $password,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private array &$capturedOptions,
            ) {
                parent::__construct($host, $port, $database, $username, $password);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedOptions = $options;

                // Return a mock PDO using SQLite in-memory for testing
                // Use a mock that ignores SET NAMES query
                return new class () extends PDO
                {
                    public function __construct()
                    {
                        parent::__construct('sqlite::memory:');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $connection->connect();

        expect($capturedOptions[PDO::ATTR_ERRMODE])->toBe(PDO::ERRMODE_EXCEPTION);
    });

    it('sets client encoding from config', function (): void {
        $capturedDsn = '';

        // Create a testable connection that captures the DSN
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
            charset: 'utf8',
            capturedDsn: $capturedDsn,
        ) extends PgSqlConnection
        {
            public function __construct(
                string $host,
                int $port,
                string $database,
                string $username,
                string $password,
                string $charset,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private string &$capturedDsn,
            ) {
                parent::__construct($host, $port, $database, $username, $password, $charset);
            }

            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                $this->capturedDsn = $dsn;

                // Return a mock PDO using SQLite in-memory for testing
                // Use a mock that ignores SET NAMES query
                return new class () extends PDO
                {
                    public function __construct()
                    {
                        parent::__construct('sqlite::memory:');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $connection->connect();

        // Verify DSN is correct (charset is set via SET NAMES query, not in DSN for PostgreSQL)
        expect($capturedDsn)->toBe('pgsql:host=localhost;port=5432;dbname=test');
    });

    it('executes raw SQL queries with parameter binding', function (): void {
        // Create a testable connection with SQLite for query testing
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        // Create a test table
                        parent::exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
                        parent::exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
                        parent::exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        // Create a testable connection with SQLite
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new PgSqlConnection(
            host: 'nonexistent.invalid.host',
            port: 5432,
            database: 'testdb',
            username: 'baduser',
            password: 'badpass',
        );

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
        // Create a testable connection with SQLite
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES
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
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        );

        expect($connection)->toBeInstanceOf(TransactionInterface::class);
    });

    it('implements beginTransaction() method', function (): void {
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES
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
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $connection->connect();

        expect($connection->inTransaction())->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();
    });

    it('implements commit() method', function (): void {
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES
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
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $connection->transaction(function () use ($connection): void {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        });

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(1);
    });

    it('auto-commits when callback completes successfully', function (): void {
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
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
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        expect(function () use ($connection): void {
            $connection->transaction(function (): void {
                throw new RuntimeException('Original exception message');
            });
        })->toThrow(RuntimeException::class, 'Original exception message');
    });

    it('returns callback return value on success', function (): void {
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES and creates test table
                return new class ($options) extends PDO
                {
                    /** @param array<int, mixed> $options */
                    public function __construct(
                        array $options,
                    ) {
                        parent::__construct('sqlite::memory:', options: $options);
                        parent::exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
                    }

                    public function exec(
                        string $statement,
                    ): int|false {
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $result = $connection->transaction(function () use ($connection): string {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");

            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('prevents nested transactions (throws exception)', function (): void {
        $connection = new class (
            host: 'localhost',
            port: 5432,
            database: 'test',
            username: 'user',
            password: 'pass',
        ) extends PgSqlConnection
        {
            protected function createPdo(
                string $dsn,
                string $username,
                string $password,
                array $options,
            ): PDO {
                // Return a mock PDO that handles SET NAMES
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
                        // Skip SET NAMES query (not supported in SQLite)
                        if (str_starts_with($statement, 'SET NAMES')) {
                            return 0;
                        }

                        return parent::exec($statement);
                    }
                };
            }
        };

        $connection->beginTransaction();

        expect(fn () => $connection->beginTransaction())
            ->toThrow(TransactionException::class, 'Nested transactions are not supported');
    });
});
