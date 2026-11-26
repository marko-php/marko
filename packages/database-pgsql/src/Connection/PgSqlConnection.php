<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Closure;
use JsonException;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\LostConnectionRecovery;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionalDdlInterface;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Connection\TransactionState;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Exceptions\TransactionConflictException;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Exceptions\ConnectionException;
use Marko\Database\PgSql\Sql\PgSqlIdentifier;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class PgSqlConnection implements ConnectionInterface, TransactionInterface, PendingAfterCommitInterface, TransactionalDdlInterface, ResettableInterface
{
    private ?PDO $pdo = null;

    private TransactionState $transactionState;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly string $charset = 'utf8',
        private readonly PgSqlExceptionTranslator $exceptionTranslator = new PgSqlExceptionTranslator(),
        private readonly TransactionBackoff $transactionBackoff = new TransactionBackoff(),
        private readonly LostConnectionRecovery $lostConnectionRecovery = new LostConnectionRecovery(),
    ) {
        $this->transactionState = new TransactionState();
    }

    public function getDsn(): string
    {
        return $this->buildDsn();
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        // The PDO is kept only once the session is set up, so a failed setup leaves the connection
        // disconnected and the next connect() tries again instead of running in the server's zone.
        try {
            $pdo = $this->createPdo(
                $this->buildDsn(),
                $this->config->username,
                $this->config->password,
                $this->getPdoOptions(),
            );

            $pdo->exec($this->getSetEncodingQuery());
        } catch (PDOException $e) {
            throw ConnectionException::connectionFailed(
                $this->config->host,
                $this->config->port,
                $this->config->database,
                $e,
            );
        }

        try {
            $pdo->exec($this->getSetTimezoneQuery());
        } catch (PDOException $e) {
            throw ConnectionException::unknownTimezone($this->config->timezone->getName(), $e);
        }

        $this->pdo = $pdo;
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

    private function buildDsn(): string
    {
        $dsn = "pgsql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}";

        if ($this->config->sslMode !== null) {
            $dsn .= ";sslmode={$this->config->sslMode}";
        }

        if ($this->config->sslRootCert !== null) {
            $dsn .= ";sslrootcert={$this->config->sslRootCert}";
        }

        if ($this->config->sslCert !== null) {
            $dsn .= ";sslcert={$this->config->sslCert}";
        }

        if ($this->config->sslKey !== null) {
            $dsn .= ";sslkey={$this->config->sslKey}";
        }

        return $dsn;
    }

    /**
     * @return array<int, mixed>
     */
    private function getPdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
    }

    private function getSetEncodingQuery(): string
    {
        return "SET NAMES '$this->charset'";
    }

    /**
     * Pin the session to database.timezone. A fixed offset is sent as an ISO interval, because PostgreSQL
     * reads a bare '+05:30' as a POSIX zone and inverts its sign. The zone was validated by DateTimeZone,
     * so it holds no quote.
     */
    private function getSetTimezoneQuery(): string
    {
        $offset = $this->config->fixedTimezoneOffset();

        return match (true) {
            $offset === '+00:00' => "SET TIME ZONE 'UTC'",
            $offset !== null => "SET TIME ZONE INTERVAL '$offset' HOUR TO MINUTE",
            default => "SET TIME ZONE '{$this->config->timezone->getName()}'",
        };
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
     * Forget the dead PDO without touching the transaction state: an open
     * transaction stays open (and marked lost) until the caller rolls it back.
     */
    private function dropPdo(): void
    {
        $this->pdo = null;
    }

    /**
     * A prepared statement lost the connection. Acts only when the statement
     * was prepared on the PDO still in use, so a stale statement from an
     * earlier connection never drops a healthy reconnected PDO or marks a
     * newer transaction lost.
     */
    private function statementLostConnection(
        PDO $preparedOn,
    ): void {
        if ($this->pdo === $preparedOn) {
            $this->lostConnectionRecovery->connectionLost($this->dropPdo(...), $this->transactionState);
        }
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->lostConnectionRecovery->run(
            function () use ($sql, $bindings): array {
                $this->ensureConnected();

                try {
                    $statement = $this->pdo->prepare($sql);
                    $this->bindValues($statement, $bindings);
                    $statement->execute();
                } catch (PDOException $e) {
                    throw $this->exceptionTranslator->translate($e, $sql, $bindings);
                }

                return $statement->fetchAll(PDO::FETCH_ASSOC);
            },
            $this->dropPdo(...),
            $this->transactionState,
        );
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        return $this->lostConnectionRecovery->run(
            function () use ($sql, $bindings): int {
                $this->ensureConnected();

                try {
                    $statement = $this->pdo->prepare($sql);
                    $this->bindValues($statement, $bindings);
                    $statement->execute();
                } catch (PDOException $e) {
                    throw $this->exceptionTranslator->translate($e, $sql, $bindings);
                }

                return $statement->rowCount();
            },
            $this->dropPdo(...),
            $this->transactionState,
        );
    }

    /**
     * @throws ConnectionException|QueryException
     */
    public function prepare(
        string $sql,
    ): StatementInterface {
        return $this->lostConnectionRecovery->run(
            function () use ($sql): StatementInterface {
                $this->ensureConnected();

                try {
                    $pdoStatement = $this->pdo->prepare($sql);
                } catch (PDOException $e) {
                    throw $this->exceptionTranslator->translate($e, $sql, []);
                }

                $preparedOn = $this->pdo;

                return new PgSqlStatement(
                    $pdoStatement,
                    $this->exceptionTranslator,
                    fn () => $this->statementLostConnection($preparedOn),
                );
            },
            $this->dropPdo(...),
            $this->transactionState,
        );
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
     * @throws ConnectionException|LostConnectionException
     */
    public function lastInsertId(): int
    {
        if ($this->transactionState->isLost()) {
            throw LostConnectionException::transactionAlreadyLost();
        }

        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    public function driverName(): string
    {
        return 'pgsql';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function supportsTransactionalDdl(): bool
    {
        return true;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return PgSqlIdentifier::quote($identifier);
    }

    /**
     * Open a transaction, or a savepoint named marko_sp_{depth} when one is
     * already open. Outside a transaction a lost connection is retried once
     * on a new connection. Inside one, a lost connection (or one already
     * marked lost) throws LostConnectionException and no statement reaches a
     * new connection until the caller rolls the transaction back.
     *
     * @throws ConnectionException|QueryException|LostConnectionException
     */
    public function beginTransaction(): void
    {
        $this->lostConnectionRecovery->run(
            function (): void {
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
            },
            $this->dropPdo(...),
            $this->transactionState,
        );
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
     * When the connection was lost, the level is closed without touching the
     * dead connection and LostConnectionException is thrown: the outcome of a
     * COMMIT that was in flight is unknown.
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
     * rolled back without sending a statement. The same holds for a level
     * marked lost (the connection is gone): nothing is sent and the connection
     * is not reopened, so the next statement reconnects once every level is
     * closed. A ROLLBACK that itself loses the connection closes the level the
     * same way and does not throw.
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    public function rollback(): void
    {
        $level = $this->transactionState->level();

        if ($level === 0) {
            throw TransactionException::notInTransaction();
        }

        if ($this->transactionState->isLost()) {
            $this->transactionState->rollback();

            return;
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
            $translated = $e instanceof PDOException ? $this->exceptionTranslator->translate($e, $statement, []) : $e;

            if ($translated instanceof LostConnectionException) {
                // The server rolled the work back with the session, which is what the caller asked for.
                $this->lostConnectionRecovery->connectionLost($this->dropPdo(...), $this->transactionState);
                $this->transactionState->rollback();

                return;
            }

            $this->transactionState->discard();

            throw $translated;
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

        if ($this->transactionState->isLost()) {
            $this->closeLevelAfterFailedCommit($level);

            throw LostConnectionException::transactionAlreadyLost();
        }

        $statement = $level === 1 ? 'COMMIT' : 'RELEASE SAVEPOINT ' . $this->savepointName($level - 1);

        try {
            $this->lostConnectionRecovery->run(
                function () use ($level, $statement): void {
                    $this->ensureConnected();

                    try {
                        if ($level === 1) {
                            $this->pdo->commit();
                        } else {
                            $this->pdo->exec($statement);
                        }
                    } catch (PDOException $e) {
                        throw $this->exceptionTranslator->translate($e, $statement, []);
                    }
                },
                $this->dropPdo(...),
                $this->transactionState,
            );
        } catch (LostConnectionException $e) {
            $this->closeLevelAfterFailedCommit($level);

            throw $e;
        } catch (Throwable $e) {
            if (
                $level === 1
                && $this->pdo?->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            $this->closeLevelAfterFailedCommit($level);

            throw $e;
        }
    }

    /**
     * Close the level whose COMMIT or RELEASE SAVEPOINT did not succeed
     * without touching the PDO: the outermost level rolls back (running its
     * after-rollback callbacks), a nested level is dropped.
     */
    private function closeLevelAfterFailedCommit(
        int $level,
    ): void {
        if ($level === 1) {
            $this->transactionState->rollback();

            return;
        }

        $this->transactionState->discard();
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
     * unconnected instance has nothing to roll back. When the server is gone
     * the PDO is dropped instead (the transaction went with the session) and
     * the next statement reconnects.
     *
     * @throws QueryException
     */
    #[Override]
    public function reset(): void
    {
        $this->transactionState->clear();

        if (
            $this->pdo === null
            || !$this->pdo->inTransaction()
        ) {
            return;
        }

        try {
            $this->pdo->rollBack();
        } catch (PDOException $e) {
            $translated = $this->exceptionTranslator->translate($e, 'ROLLBACK', []);

            if (!$translated instanceof LostConnectionException) {
                throw $translated;
            }

            // The server is gone and took the transaction with it: nothing is left to roll back.
            $this->dropPdo();
        }
    }

    private function savepointName(
        int $depth,
    ): string {
        return "marko_sp_$depth";
    }
}
