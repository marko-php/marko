<?php

declare(strict_types=1);

namespace Marko\Queue\Database\Tests\Fixtures;

use Closure;
use LogicException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use PDO;
use Throwable;

/**
 * In-memory SQLite connection for exercising the database queue against real SQL.
 *
 * The queue builds its SELECT with a driver query builder (the PostgreSQL one in
 * these tests, whose quoting and LIMIT SQLite accepts). SQLite has no row locks:
 * a write transaction locks the whole database. So query() records a trailing
 * row-lock clause in $lockClauses, for tests to assert on, and drops it before
 * running the statement.
 */
class SqliteConnection implements ConnectionInterface, TransactionInterface
{
    /**
     * Row-lock clauses (e.g. "FOR UPDATE SKIP LOCKED") stripped from queries, in order.
     *
     * @var list<string>
     */
    public array $lockClauses = [];

    /**
     * Every statement run through query() or execute(), in order.
     *
     * @var list<string>
     */
    public array $statements = [];

    public string $identifierDelimiter = '"';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Create a connection with the jobs and failed_jobs tables. SQLite is not a supported driver, so there is no
     * generator to build them from the entities; this is the same DDL the MySQL and PostgreSQL generators write.
     */
    public static function withQueueTables(
        string $jobsTable = 'jobs',
    ): self {
        $connection = new self();
        $jobs = $connection->quoteIdentifier($jobsTable);
        $connection->execute(<<<SQL
            CREATE TABLE $jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL DEFAULT 'default',
                payload TEXT NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                reserved_at TIMESTAMP NULL,
                available_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);
        $connection->execute("CREATE INDEX jobs_queue_available_at_index ON $jobs (queue, available_at)");
        $connection->execute(<<<'SQL'
            CREATE TABLE failed_jobs (
                id VARCHAR(36) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL);

        return $connection;
    }

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
        if (preg_match('/ (FOR (?:UPDATE|SHARE)(?: SKIP LOCKED| NOWAIT)?)$/', $sql, $matches) === 1) {
            $this->lockClauses[] = $matches[1];
            $sql = substr($sql, 0, -strlen($matches[0]));
        }

        $this->statements[] = $sql;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->statements[] = $sql;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new LogicException('prepare() is not used by the database queue');
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    /**
     * SQLite accepts both delimiters, so a test can set $identifierDelimiter to a backtick and tell SQL quoted
     * through the connection from SQL that picked its own delimiter.
     */
    public function quoteIdentifier(
        string $identifier,
    ): string {
        $delimiter = $this->identifierDelimiter;

        return $delimiter . str_replace($delimiter, $delimiter . $delimiter, $identifier) . $delimiter;
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * @throws Throwable
     */
    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();

            return $result;
        } catch (Throwable $e) {
            $this->rollback();

            throw $e;
        }
    }

    public function transactionLevel(): int
    {
        return $this->pdo->inTransaction() ? 1 : 0;
    }

    public function afterCommit(
        callable $callback,
    ): void {
        if (!$this->pdo->inTransaction()) {
            $callback();

            return;
        }

        throw new LogicException('SqliteConnection does not queue after-commit callbacks');
    }

    public function afterRollback(
        callable $callback,
    ): void {
        if ($this->pdo->inTransaction()) {
            throw new LogicException('SqliteConnection does not queue after-rollback callbacks');
        }
    }
}
