<?php

declare(strict_types=1);

namespace Marko\Testing\Exceptions;

use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Exceptions\MarkoException;

class DatabaseTestException extends MarkoException
{
    public static function databasePackageMissing(
        string $interface,
    ): self {
        return new self(
            message: "Database test helpers need marko/database, but '$interface' does not exist.",
            context: 'Booting a TestDatabase (RefreshDatabase or TruncateDatabase)',
            suggestion: 'Install the database layer and a driver: composer require marko/database-pgsql (or marko/database-mysql or marko/database-sqlite).',
        );
    }

    public static function noDriver(
        string $basePath,
    ): self {
        return new self(
            message: "No database driver is installed in the application at $basePath.",
            context: 'TestDatabase::boot() found no binding for Marko\Database\Connection\ConnectionInterface or TransactionInterface',
            suggestion: 'Install a driver package such as marko/database-pgsql, marko/database-mysql or marko/database-sqlite.',
        );
    }

    public static function productionEnvironment(
        string $environment,
    ): self {
        return new self(
            message: "Refusing to run database tests in the '$environment' environment.",
            context: 'TestDatabase migrates the database and RefreshDatabase/TruncateDatabase change its data; an unset APP_ENV counts as production.',
            suggestion: 'Set APP_ENV=testing for the test run (for example <env name="APP_ENV" value="testing"/> in phpunit.xml) and point config/database.php at a dedicated test database.',
        );
    }

    public static function destructiveInEnvironment(
        string $operation,
        string $environment,
    ): self {
        return new self(
            message: "Refusing to $operation in the '$environment' environment.",
            context: 'This operation deletes data. It runs only when APP_ENV is a testing environment ('
                . implode(', ', AppEnvironment::TESTING_NAMES) . ').',
            suggestion: 'Set APP_ENV=testing for the test run (MARKO_ENV, when set, takes precedence over APP_ENV) '
                . 'and point config/database.php at a dedicated test database.',
        );
    }

    public static function conflictingFresh(
        string $basePath,
        bool $fresh,
    ): self {
        $first = $fresh ? 'without' : 'with';
        $now = $fresh ? 'with' : 'without';

        return new self(
            message: "TestDatabase for $basePath was booted $first fresh: true earlier in this process, and now $now it.",
            context: 'The database is booted, and optionally rebuilt, once per process; later boot() calls reuse it.',
            suggestion: 'Pass the same fresh value everywhere, typically once from tests/Pest.php.',
        );
    }

    public static function testTransactionAlreadyOpen(
        int $level,
    ): self {
        return new self(
            message: "A test transaction is already open (transaction level $level).",
            context: 'RefreshDatabase::begin() was called again before rollback().',
            suggestion: 'Call rollback() after every test (afterEach / tearDown), and begin() once before each test.',
        );
    }

    public static function noTestTransaction(
        string $operation,
    ): self {
        return new self(
            message: "Cannot $operation: no test transaction is open.",
            context: "RefreshDatabase::$operation() was called before begin() or after rollback().",
            suggestion: 'Call begin() before each test (beforeEach / setUp).',
        );
    }

    public static function cannotRunPendingAfterCommitCallbacks(
        string $connectionClass,
    ): self {
        return new self(
            message: "Connection '$connectionClass' cannot run pending after-commit callbacks.",
            context: 'RefreshDatabase::runAfterCommitCallbacks() needs a connection implementing Marko\Database\Connection\PendingAfterCommitInterface.',
            suggestion: 'Use the marko/database-pgsql, marko/database-mysql or marko/database-sqlite driver, or assert on after-commit work with TruncateDatabase and a real commit.',
        );
    }

    public static function truncateInsideTransaction(
        int $level,
    ): self {
        return new self(
            message: "Cannot truncate while a transaction is open (transaction level $level).",
            context: 'TruncateDatabase::truncate() was called inside a transaction; MySQL commits implicitly on TRUNCATE and SQLite ignores the foreign_keys pragma.',
            suggestion: 'Do not combine TruncateDatabase with RefreshDatabase in the same test; call truncate() before begin() or use one strategy per test file.',
        );
    }

    public static function unsupportedDriver(
        string $driver,
    ): self {
        return new self(
            message: "TruncateDatabase does not support the '$driver' driver.",
            context: 'Building the TRUNCATE statements for the entity tables',
            suggestion: 'Use the pgsql, mysql or sqlite driver, or use RefreshDatabase, which works with any transactional driver.',
        );
    }
}
