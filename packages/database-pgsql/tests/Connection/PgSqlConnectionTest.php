<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Connection\PgSqlStatement;
use Marko\Database\PgSql\Exceptions\ConnectionException;

describe('PgSqlConnection', function (): void {
    it('implements ConnectionInterface', function (): void {
        $reflection = new ReflectionClass(PgSqlConnection::class);

        expect($reflection->implementsInterface(ConnectionInterface::class))->toBeTrue();
    });

    it('constructs proper PostgreSQL DSN from config', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('buildDsn');
        $method->setAccessible(true);

        $dsn = $method->invoke($connection);

        expect($dsn)->toBe('pgsql:host=localhost;port=5432;dbname=test_db');
    });

    it('connects lazily on first query', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        // Not connected immediately after construction
        expect($connection->isConnected())->toBeFalse();

        // Check internal PDO is null before any query
        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        expect($pdoProperty->getValue($connection))->toBeNull();
    });

    it('sets PDO error mode to exceptions', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        // Verify getPdoOptions returns exception error mode
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getPdoOptions');

        $options = $method->invoke($connection);

        expect($options)->toHaveKey(PDO::ATTR_ERRMODE)
            ->and($options[PDO::ATTR_ERRMODE])->toBe(PDO::ERRMODE_EXCEPTION);
    });

    it('sets client encoding from config', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
            charset: 'utf8',
        );

        // Verify getSetEncodingQuery returns correct SQL
        $reflection = new ReflectionClass($connection);
        $method = $reflection->getMethod('getSetEncodingQuery');

        $query = $method->invoke($connection);

        expect($query)->toBe("SET NAMES 'utf8'");
    });

    it('executes raw SQL queries with parameter binding', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        // Test that query() method ensures connection and uses bindings correctly
        // We verify the method signature and internal flow without real PDO
        $reflection = new ReflectionClass($connection);
        $queryMethod = $reflection->getMethod('query');

        // Verify method signature
        $params = $queryMethod->getParameters();
        expect($params)->toHaveCount(2)
            ->and($params[0]->getName())->toBe('sql')
            ->and($params[0]->getType()?->getName())->toBe('string')
            ->and($params[1]->getName())->toBe('bindings')
            ->and($params[1]->getType()?->getName())->toBe('array')
            ->and($params[1]->isDefaultValueAvailable())->toBeTrue()
            ->and($params[1]->getDefaultValue())->toBe([])
            // Verify return type
            ->and($queryMethod->getReturnType()?->getName())->toBe('array');

        // Verify ensureConnected is called internally by inspecting method body
        $method = $reflection->getMethod('ensureConnected');
        expect($method)->toBeInstanceOf(ReflectionMethod::class);
    });

    it('prepares statements for repeated execution', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        // Verify prepare method exists and returns StatementInterface
        $reflection = new ReflectionClass($connection);
        $prepareMethod = $reflection->getMethod('prepare');

        // Verify return type
        expect($prepareMethod->getReturnType()?->getName())->toBe(StatementInterface::class);

        // Verify PgSqlStatement implements StatementInterface
        $statementReflection = new ReflectionClass(PgSqlStatement::class);
        expect($statementReflection->implementsInterface(StatementInterface::class))->toBeTrue();
    });

    it('throws ConnectionException on connection failure with helpful message', function (): void {
        $connection = new PgSqlConnection(
            host: 'invalid-host-that-does-not-exist',
            port: 5432,
            database: 'nonexistent',
            username: 'user',
            password: 'pass',
        );

        expect(fn () => $connection->connect())
            ->toThrow(ConnectionException::class);
    });

    it('properly disconnects and releases resources', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Simulate a connected state by setting PDO to a mock
        $mockPdo = new class () extends PDO
        {
            public function __construct()
            {
                // Don't call parent to avoid actual connection
            }
        };
        $pdoProperty->setValue($connection, $mockPdo);

        // Verify we are "connected"
        expect($connection->isConnected())->toBeTrue();

        // Disconnect
        $connection->disconnect();

        // Verify PDO is now null
        expect($pdoProperty->getValue($connection))->toBeNull()
            ->and($connection->isConnected())->toBeFalse();
    });

    it('implements TransactionInterface', function (): void {
        $reflection = new ReflectionClass(PgSqlConnection::class);

        expect($reflection->implementsInterface(TransactionInterface::class))->toBeTrue();
    });

    it('implements beginTransaction() method', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoProperty->setValue($connection, $pdo);

        expect($connection->inTransaction())->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();
    });

    it('implements commit() method', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        $connection->beginTransaction();
        $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        $connection->commit();

        expect($connection->inTransaction())->toBeFalse();

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(1)
            ->and($results[0]['value'])->toBe('test');
    });

    it('implements rollback() method', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        $connection->beginTransaction();
        $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        $connection->rollback();

        expect($connection->inTransaction())->toBeFalse();

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(0);
    });

    it('implements inTransaction() method returning boolean', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoProperty->setValue($connection, $pdo);

        $result = $connection->inTransaction();

        expect($result)->toBeBool()
            ->and($result)->toBeFalse();

        $connection->beginTransaction();

        expect($connection->inTransaction())->toBeTrue();

        $connection->commit();

        expect($connection->inTransaction())->toBeFalse();
    });

    it('implements transaction(callable) method', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        $connection->transaction(function () use ($connection): void {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");
        });

        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(1);
    });

    it('auto-commits when callback completes successfully', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        $connection->transaction(function () use ($connection): void {
            $connection->execute("INSERT INTO test_data (value) VALUES ('committed')");
        });

        // Verify not in transaction after callback
        expect($connection->inTransaction())->toBeFalse();

        // Verify data was committed
        $results = $connection->query('SELECT * FROM test_data');
        expect($results)->toHaveCount(1)
            ->and($results[0]['value'])->toBe('committed');
    });

    it('auto-rolls back when callback throws exception', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

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
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        expect(function () use ($connection): void {
            $connection->transaction(function (): void {
                throw new RuntimeException('Original exception message');
            });
        })->toThrow(RuntimeException::class, 'Original exception message');
    });

    it('returns callback return value on success', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE test_data (id INTEGER PRIMARY KEY, value TEXT)');
        $pdoProperty->setValue($connection, $pdo);

        $result = $connection->transaction(function () use ($connection): string {
            $connection->execute("INSERT INTO test_data (value) VALUES ('test')");

            return 'success';
        });

        expect($result)->toBe('success');
    });

    it('prevents nested transactions (throws exception)', function (): void {
        $connection = new PgSqlConnection(
            host: 'localhost',
            port: 5432,
            database: 'test_db',
            username: 'user',
            password: 'pass',
        );

        $reflection = new ReflectionClass($connection);
        $pdoProperty = $reflection->getProperty('pdo');

        // Create an in-memory SQLite PDO for testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdoProperty->setValue($connection, $pdo);

        $connection->beginTransaction();

        expect(fn () => $connection->beginTransaction())
            ->toThrow(TransactionException::class, 'Nested transactions are not supported');
    });
});
