<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Connection;

use Closure;
use JsonException;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Support\ErrorCapture;
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
use Marko\Database\Sqlite\Exceptions\ConnectionException;
use Marko\Database\Sqlite\Sql\SqliteIdentifier;
use Override;
use PDO;
use Pdo\Sqlite;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Datetimes are stored as written: Marko writes them in `database.timezone`, because SQLite has no
 * session time zone. SQLite's own CURRENT_TIMESTAMP is always UTC.
 */
class SqliteConnection implements ConnectionInterface, TransactionInterface, PendingAfterCommitInterface, ResettableInterface
{
    private const string MEMORY = ':memory:';

    private const string MINIMUM_VERSION = '3.39.0';

    /**
     * Applied on every connect, in this order. `sqlite.pragmas` merges over them.
     *
     * @var array<string, string|int>
     */
    private const array DEFAULT_PRAGMAS = [
        'journal_mode' => 'WAL',
        'busy_timeout' => 5000,
        'synchronous' => 'NORMAL',
        'foreign_keys' => 'ON',
        'temp_store' => 'MEMORY',
    ];

    /**
     * @var list<string>
     */
    private const array ALLOWED_PRAGMAS = [
        'journal_mode',
        'busy_timeout',
        'synchronous',
        'foreign_keys',
        'temp_store',
        'cache_size',
        'wal_autocheckpoint',
        'mmap_size',
        'locking_mode',
        'auto_vacuum',
        'journal_size_limit',
        'recursive_triggers',
        'secure_delete',
    ];

    private const string NO_TRANSACTION_MESSAGE = 'no transaction is active';

    private const string PRAGMA_VALUE_PATTERN = '/^(?:-?\d+|[A-Za-z_]+)$/';

    private ?PDO $pdo = null;

    private TransactionState $transactionState;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly SqliteExceptionTranslator $exceptionTranslator = new SqliteExceptionTranslator(),
        private readonly TransactionBackoff $transactionBackoff = new TransactionBackoff(),
    ) {
        $this->transactionState = new TransactionState();
    }

    public function getDsn(): string
    {
        return 'sqlite:' . $this->config->database;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        $this->prepareDatabaseFile();

        // The PDO is kept only once setup succeeded, so a failed setup leaves the connection disconnected.
        try {
            $pdo = $this->createPdo($this->getDsn(), $this->getPdoOptions());
            $version = $this->sqliteVersion($pdo);

            if (version_compare($version, self::MINIMUM_VERSION, '<')) {
                throw ConnectionException::unsupportedVersion($version, self::MINIMUM_VERSION);
            }

            foreach ($this->resolvePragmas() as $name => $value) {
                $pdo->exec("PRAGMA $name = $value");
            }
        } catch (PDOException $e) {
            throw ConnectionException::connectionFailed($this->config->database, $e);
        }

        $this->pdo = $pdo;
    }

    /**
     * Create a PDO instance. Override in tests.
     *
     * @param array<int, mixed> $options PDO options
     */
    protected function createPdo(
        string $dsn,
        array $options,
    ): PDO {
        return new PDO($dsn, null, null, $options);
    }

    protected function sqliteVersion(
        PDO $pdo,
    ): string {
        return (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
    }

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
     * @param array<int|string, mixed> $bindings
     *
     * @throws ConnectionException|QueryException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @throws ConnectionException|QueryException
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        return $this->run($sql, $bindings)->rowCount();
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

        return new SqliteStatement($pdoStatement, $this->exceptionTranslator);
    }

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @throws ConnectionException|QueryException
     */
    private function run(
        string $sql,
        array $bindings,
    ): PDOStatement {
        $this->ensureConnected();

        try {
            $statement = $this->pdo->prepare($sql);
            $this->bindValues($statement, $bindings);
            $statement->execute();
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $sql, $bindings);
        }

        return $statement;
    }

    /**
     * SQLite has no boolean type, so booleans bind as 0/1. Arrays are stored as JSON text.
     *
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
                    $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (JsonException $e) {
                    throw ConnectionException::invalidArrayBinding($param, $e);
                }
            }

            if (is_bool($value)) {
                $value = (int) $value;
            }

            $type = match (true) {
                is_null($value) => PDO::PARAM_NULL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($param, $value, $type);
        }
    }

    /**
     * @throws ConnectionException
     */
    public function lastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Open a transaction with BEGIN IMMEDIATE, so the writer takes the database write lock up front
     * instead of failing on a read-to-write upgrade, or a savepoint named marko_sp_{depth} when one
     * is already open.
     *
     * Every statement goes through exec(): PDO's own transaction methods rely on a transaction flag
     * that a raw BEGIN does not reliably set, and SQLite can end a transaction on its own.
     *
     * @throws ConnectionException|QueryException
     */
    public function beginTransaction(): void
    {
        $this->ensureConnected();

        $level = $this->transactionState->level();
        $statement = $level === 0 ? 'BEGIN IMMEDIATE' : 'SAVEPOINT ' . $this->savepointName($level);

        try {
            $this->pdo->exec($statement);
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $statement, []);
        }

        $this->transactionState->begin();
    }

    /**
     * Commit the innermost level: COMMIT at the outermost level, RELEASE SAVEPOINT when nested.
     * After-commit callbacks run once the outermost level commits.
     *
     * @throws ConnectionException|TransactionException|QueryException|Throwable
     */
    public function commit(): void
    {
        $this->commitStatement();
        $this->transactionState->commit();
    }

    /**
     * Roll back the innermost level: ROLLBACK at the outermost level, ROLLBACK TO SAVEPOINT when
     * nested. The level's after-commit callbacks are discarded and its after-rollback callbacks run.
     *
     * When SQLite has already ended the transaction (disk full, I/O error, interrupt), there is
     * nothing left to roll back: the level is closed as rolled back.
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
            $this->pdo->exec($statement);
        } catch (PDOException $e) {
            if (!$this->isTransactionAlreadyEnded($e, $level)) {
                $this->transactionState->discard();

                throw $this->exceptionTranslator->translate($e, $statement, []);
            }
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
     * Run the callback in a transaction, retrying the outermost transaction on a
     * TransactionConflictException (SQLITE_BUSY_SNAPSHOT) up to $attempts runs in total, and waiting
     * between attempts as $backoff says (see TransactionBackoff). A LockTimeoutException is never retried.
     *
     * The COMMIT statement is sent apart from running the after-commit callbacks, so a conflict raised
     * by COMMIT is retried while an exception from an after-commit callback is not.
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

                if ($e instanceof TransactionConflictException && $attempt < $maxAttempts) {
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
     * Send COMMIT (outermost level) or RELEASE SAVEPOINT (nested level). On success the level stays
     * open in the transaction state and the caller closes it. On failure the level is closed (rolled
     * back at the outermost level, running its after-rollback callbacks) and the translated error is thrown.
     *
     * @throws ConnectionException|TransactionException|QueryException|PDOException
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
            $this->pdo->exec($statement);
        } catch (PDOException $e) {
            if ($level === 1) {
                $this->rollbackOutermostQuietly();
                $this->transactionState->rollback();
            } else {
                $this->transactionState->discard();
            }

            throw $this->exceptionTranslator->translate($e, $statement, []);
        }
    }

    /**
     * Roll back the level a failed callback ran in. When that rollback fails too, the callback's
     * TransactionConflictException wins so the outermost transaction() can still retry it.
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

    /**
     * @throws PDOException
     */
    private function rollbackOutermostQuietly(): void
    {
        try {
            $this->pdo->exec('ROLLBACK');
        } catch (PDOException $e) {
            if (!$this->isTransactionAlreadyEnded($e, 1)) {
                throw $e;
            }
        }
    }

    private function isTransactionAlreadyEnded(
        PDOException $e,
        int $level,
    ): bool {
        return $level === 1 && str_contains($e->getMessage(), self::NO_TRANSACTION_MESSAGE);
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
     * Rolls back a transaction abandoned by a request that threw before commit()/rollback(), so a
     * long-running worker never carries it into the next request. Pending callbacks are dropped
     * without running. Never opens a connection: an unconnected instance has nothing to roll back.
     *
     * @throws PDOException
     */
    #[Override]
    public function reset(): void
    {
        $abandoned = $this->transactionState->level() > 0;
        $this->transactionState->clear();

        if ($abandoned && $this->pdo !== null) {
            $this->rollbackOutermostQuietly();
        }
    }

    private function savepointName(
        int $depth,
    ): string {
        return "marko_sp_$depth";
    }

    public function driverName(): string
    {
        return 'sqlite';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return SqliteIdentifier::quote($identifier);
    }

    /**
     * @throws ConnectionException
     */
    private function prepareDatabaseFile(): void
    {
        $path = $this->config->database;

        if ($path === self::MEMORY || str_starts_with($path, 'file:') || file_exists($path)) {
            return;
        }

        if (!$this->config->sqliteCreateIfMissing) {
            throw ConnectionException::databaseFileNotFound($path);
        }

        $directory = dirname($path);

        if (is_dir($directory)) {
            return;
        }

        $created = ErrorCapture::run($reason, fn (): bool => mkdir($directory, 0775, true));

        if (!$created && !is_dir($directory)) {
            throw ConnectionException::directoryCreationFailed($directory, $reason);
        }
    }

    /**
     * Defaults merged with the configured overrides, each name and value validated before it reaches SQL.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function resolvePragmas(): array
    {
        /** @var array<string, mixed> $pragmas */
        $pragmas = array_merge(self::DEFAULT_PRAGMAS, $this->config->sqlitePragmas);

        // WAL is a property of the file and reports 'memory' for an in-memory database.
        if ($this->config->database === self::MEMORY) {
            unset($pragmas['journal_mode']);
        }

        foreach ($pragmas as $name => $value) {
            if (!in_array($name, self::ALLOWED_PRAGMAS, true)) {
                throw ConnectionException::unknownPragma((string) $name, implode(', ', self::ALLOWED_PRAGMAS));
            }

            if (is_bool($value)) {
                $pragmas[$name] = $value ? 1 : 0;

                continue;
            }

            if ((!is_int($value) && !is_string($value)) || preg_match(
                self::PRAGMA_VALUE_PATTERN,
                (string) $value,
            ) !== 1) {
                throw ConnectionException::unsafePragmaValue($name, 'expected an integer or a bare word');
            }
        }

        return $pragmas;
    }

    /**
     * pdo_sqlite reports the primary result code (19 for every constraint) unless extended result codes
     * are enabled, which the exception translator needs to tell a UNIQUE violation from a NOT NULL one.
     *
     * @return array<int, mixed>
     */
    private function getPdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            Sqlite::ATTR_EXTENDED_RESULT_CODES => true,
        ];
    }
}
