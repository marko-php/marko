<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

use Closure;
use JsonException;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Connection\TransactionState;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\MySql\Exceptions\ConnectionException;
use Marko\Database\MySql\Exceptions\ServerVersionException;
use Marko\Database\MySql\Sql\MySqlIdentifier;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class MySqlConnection implements ConnectionInterface, TransactionInterface, PendingAfterCommitInterface, ResettableInterface
{
    /** ER_UNKNOWN_TIME_ZONE: the server has no time zone tables, or none for this zone. */
    private const int ERROR_UNKNOWN_TIME_ZONE = 1298;

    /** The first MariaDB release with INSERT ... RETURNING. */
    private const string MARIADB_RETURNING_VERSION = '10.5';

    private ?PDO $pdo = null;

    private ?MySqlServer $server = null;

    private TransactionState $transactionState;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly string $charset = 'utf8mb4',
        private readonly MySqlExceptionTranslator $exceptionTranslator = new MySqlExceptionTranslator(),
        private readonly TransactionBackoff $transactionBackoff = new TransactionBackoff(),
    ) {
        $this->transactionState = new TransactionState();
    }

    public function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->host,
            $this->config->port,
            $this->config->database,
            $this->charset,
        );
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Real server-side prepares: values travel apart from the SQL text, and errors never echo them.
            PDO::ATTR_EMULATE_PREPARES => false,
            // One statement per call, so SQL concatenated into a query cannot stack a second one.
            PDO\Mysql::ATTR_MULTI_STATEMENTS => false,
            PDO\Mysql::ATTR_INIT_COMMAND => $this->getSetTimezoneQuery(),
        ];

        if ($this->config->sslRootCert !== null) {
            $options[PDO\Mysql::ATTR_SSL_CA] = $this->config->sslRootCert;
            $options[PDO\Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = $this->config->sslVerifyServerCert;
        }

        if ($this->config->sslCert !== null) {
            $options[PDO\Mysql::ATTR_SSL_CERT] = $this->config->sslCert;
        }

        if ($this->config->sslKey !== null) {
            $options[PDO\Mysql::ATTR_SSL_KEY] = $this->config->sslKey;
        }

        try {
            $this->pdo = $this->createPdo(
                $this->getDsn(),
                $this->config->username,
                $this->config->password,
                $options,
            );
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? $e->getCode()) === self::ERROR_UNKNOWN_TIME_ZONE) {
                throw ConnectionException::unknownTimezone($this->getSessionTimezone(), $e);
            }

            throw ConnectionException::connectionFailed(
                $this->config->host,
                $this->config->port,
                $this->config->database,
                $e,
            );
        }
    }

    /**
     * The session time zone: database.timezone as a fixed offset (`+00:00` for UTC) when it has one, which
     * needs no time zone tables, or the zone name, which does.
     */
    private function getSessionTimezone(): string
    {
        return $this->config->fixedTimezoneOffset() ?? $this->config->timezone->getName();
    }

    /**
     * Run by the server on every new connection (PDO\Mysql::ATTR_INIT_COMMAND), so a reconnect is pinned too.
     * The zone was validated by DateTimeZone, so it holds no quote.
     */
    private function getSetTimezoneQuery(): string
    {
        return "SET time_zone = '{$this->getSessionTimezone()}'";
    }

    /**
     * Create a PDO instance. Override in tests.
     *
     * @param string $dsn The DSN string
     * @param string $username Database username
     * @param string $password Database password
     * @param array<int, mixed> $options PDO options
     * @return PDO The PDO instance
     */
    protected function createPdo(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        return new PDO($dsn, $username, $password, $options);
    }

    /**
     * Dropping the connection ends any open transaction on the server, so the
     * transaction depth and pending callbacks are discarded with it.
     */
    public function disconnect(): void
    {
        $this->transactionState->clear();
        $this->pdo = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * @throws ConnectionException
     */
    private function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->ensureConnected();

        try {
            $statement = $this->pdo->prepare($sql);
            $this->bindValues($statement, $bindings);
            $statement->execute();
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $sql, $bindings);
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->ensureConnected();

        try {
            $statement = $this->pdo->prepare($sql);
            $this->bindValues($statement, $bindings);
            $statement->execute();
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $sql, $bindings);
        }

        return $statement->rowCount();
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @throws ConnectionException
     */
    private function bindValues(
        PDOStatement $statement,
        array $bindings,
    ): void {
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;

            if (is_array($value)) {
                try {
                    $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (JsonException $e) {
                    throw ConnectionException::invalidArrayBinding($param, $e);
                }

                $statement->bindValue($param, $encoded, PDO::PARAM_STR);

                continue;
            }

            $type = match (true) {
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function prepare(
        string $sql,
    ): StatementInterface {
        $this->ensureConnected();

        try {
            $pdoStatement = $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $sql, []);
        }

        return new MySqlStatement($pdoStatement, $this->exceptionTranslator);
    }

    /**
     * @throws ConnectionException
     */
    public function lastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'mysql';
    }

    /**
     * MariaDB has INSERT ... RETURNING (single and multi-row) since 10.5. MySQL has none.
     *
     * The server is read once per connection (see server()), so the first call connects.
     *
     * @throws ConnectionException|QueryException|ServerVersionException
     */
    public function supportsReturning(): bool
    {
        $version = $this->server()->version();

        return $version->isMariaDb
            && $version->isAtLeast(self::MARIADB_RETURNING_VERSION);
    }

    /**
     * The server this connection talks to, MySQL or MariaDB. It is built on first use and kept with the
     * connection, so the version is read once (on the first question asked of it). The container's shared
     * MySqlServer is this same instance (see module.php), so the query builders and the introspector share it.
     */
    public function server(): MySqlServer
    {
        return $this->server ??= new MySqlServer($this);
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return MySqlIdentifier::quote($identifier);
    }

    /**
     * Open a transaction, or a savepoint named marko_sp_{depth} when one is
     * already open.
     *
     * @throws ConnectionException|QueryException
     */
    public function beginTransaction(): void
    {
        $this->ensureConnected();

        $level = $this->transactionState->level();
        $statement = $level === 0 ? 'BEGIN' : 'SAVEPOINT ' . $this->savepointName($level);

        try {
            if ($level === 0) {
                $this->pdo->beginTransaction();
            } else {
                $this->pdo->exec($statement);
            }
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $statement, []);
        }

        $this->transactionState->begin();
    }

    /**
     * Commit the innermost level: COMMIT at the outermost level, RELEASE
     * SAVEPOINT when nested. After-commit callbacks run once the outermost
     * level commits.
     *
     * When the outermost COMMIT fails, the transaction is rolled back (if the
     * server left it open), the after-rollback callbacks run, and the COMMIT
     * error is rethrown, translated like any other statement error (so a
     * conflict detected at COMMIT is a TransactionConflictException).
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    public function commit(): void
    {
        $this->commitStatement();
        $this->transactionState->commit();
    }

    /**
     * Roll back the innermost level: ROLLBACK at the outermost level,
     * ROLLBACK TO SAVEPOINT when nested. The level's after-commit callbacks
     * are discarded and its after-rollback callbacks run.
     *
     * When the server has already ended the transaction (MySQL does this on a
     * deadlock), there is nothing left to roll back: the level is closed as
     * rolled back without sending a statement.
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    public function rollback(): void
    {
        $level = $this->transactionState->level();

        if ($level === 0) {
            throw TransactionException::notInTransaction();
        }

        $this->ensureConnected();
        $statement = $level === 1 ? 'ROLLBACK' : 'ROLLBACK TO SAVEPOINT ' . $this->savepointName($level - 1);

        try {
            if ($this->pdo->inTransaction()) {
                if ($level === 1) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec($statement);
                }
            }
        } catch (Throwable $e) {
            $this->transactionState->discard();

            throw $e instanceof PDOException ? $this->exceptionTranslator->translate($e, $statement, []) : $e;
        }

        $this->transactionState->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->transactionState->level() > 0;
    }

    public function transactionLevel(): int
    {
        return $this->transactionState->level();
    }

    /**
     * Run the callback in a transaction, retrying the outermost transaction
     * on a TransactionConflictException up to $attempts runs in total, and
     * waiting between attempts as $backoff says (see TransactionBackoff).
     *
     * The COMMIT statement is sent apart from running the after-commit
     * callbacks, so a conflict raised by COMMIT is retried while an exception
     * from an after-commit callback (the data is already committed) is not.
     * A failed COMMIT is never followed by a second rollback attempt
     * (commitStatement() already cleans up after itself).
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        if ($attempts < 1) {
            throw TransactionException::invalidAttempts($attempts);
        }

        $this->transactionBackoff->validate($backoff);

        // A nested call never retries: the outermost transaction() owns the retry.
        $maxAttempts = $this->transactionState->level() === 0 ? $attempts : 1;

        for ($attempt = 1; ; $attempt++) {
            $this->beginTransaction();

            try {
                $result = $callback();
            } catch (Throwable $e) {
                $this->rollbackAfterFailure($e);

                if (
                    $e instanceof TransactionConflictException
                    && $attempt < $maxAttempts
                ) {
                    $this->transactionBackoff->wait($attempt, $backoff, $e);

                    continue;
                }

                throw $e;
            }

            try {
                $this->commitStatement();
            } catch (TransactionConflictException $e) {
                if ($attempt < $maxAttempts) {
                    $this->transactionBackoff->wait($attempt, $backoff, $e);

                    continue;
                }

                throw $e;
            }

            $this->transactionState->commit();

            return $result;
        }
    }

    /**
     * Send COMMIT (outermost level) or RELEASE SAVEPOINT (nested level). On
     * success the level stays open in the transaction state, and the caller
     * closes it with TransactionState::commit(), which runs the after-commit
     * callbacks. On failure the level is closed (rolled back at the outermost
     * level, running its after-rollback callbacks) and the translated error
     * is thrown.
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    private function commitStatement(): void
    {
        $level = $this->transactionState->level();

        if ($level === 0) {
            throw TransactionException::notInTransaction();
        }

        $this->ensureConnected();
        $statement = $level === 1 ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $this->savepointName($level - 1);

        try {
            if ($level === 1) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec($statement);
            }
        } catch (Throwable $e) {
            if ($level === 1) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                $this->transactionState->rollback();
            } else {
                $this->transactionState->discard();
            }

            throw $e instanceof PDOException ? $this->exceptionTranslator->translate($e, $statement, []) : $e;
        }
    }

    /**
     * Roll back the level a failed callback ran in. When that rollback fails
     * too, the callback's TransactionConflictException wins: the rollback
     * error is a consequence of the conflict (the level is closed either
     * way), and the outermost transaction() must still see the conflict to
     * retry it. Any other failure keeps the rollback error.
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    private function rollbackAfterFailure(
        Throwable $failure,
    ): void {
        try {
            $this->rollback();
        } catch (Throwable $rollbackError) {
            if (!$failure instanceof TransactionConflictException) {
                throw $rollbackError;
            }
        }
    }

    public function afterCommit(
        callable $callback,
    ): void {
        $this->transactionState->afterCommit($callback);
    }

    public function afterRollback(
        callable $callback,
    ): void {
        $this->transactionState->afterRollback($callback);
    }

    public function runPendingAfterCommitCallbacks(): void
    {
        $this->transactionState->runAfterCommitCallbacks();
    }

    /**
     * Rolls back a transaction abandoned by a request that threw before
     * commit()/rollback(), so a long-running worker never carries it into
     * the next request. Pending callbacks are dropped without running: they
     * belong to the abandoned request. Never opens a connection: an
     * unconnected instance has nothing to roll back.
     */
    #[Override]
    public function reset(): void
    {
        $this->transactionState->clear();

        if (
            $this->pdo !== null
            && $this->pdo->inTransaction()
        ) {
            $this->pdo->rollBack();
        }
    }

    private function savepointName(
        int $depth,
    ): string {
        return "marko_sp_$depth";
    }
}
