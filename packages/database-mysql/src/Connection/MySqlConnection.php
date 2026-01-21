<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\MySql\Exceptions\ConnectionException;
use PDO;
use PDOException;
use Throwable;

class MySqlConnection implements ConnectionInterface, TransactionInterface
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8mb4',
    ) {}

    public function getDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
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
        ];

        try {
            $this->pdo = $this->createPdo(
                $this->getDsn(),
                $this->username,
                $this->password,
                $options,
            );
        } catch (PDOException $e) {
            throw ConnectionException::connectionFailed(
                $this->host,
                $this->port,
                $this->database,
                $e,
            );
        }
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

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * @throws ConnectionException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->connect();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ConnectionException
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->connect();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * @throws ConnectionException
     */
    public function prepare(
        string $sql,
    ): StatementInterface {
        $this->connect();

        $pdoStatement = $this->pdo->prepare($sql);

        return new MySqlStatement($pdoStatement);
    }

    /**
     * @throws ConnectionException
     */
    public function lastInsertId(): int
    {
        $this->connect();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @throws ConnectionException|TransactionException
     */
    public function beginTransaction(): void
    {
        $this->connect();

        if ($this->pdo->inTransaction()) {
            throw TransactionException::nestedTransactionNotSupported();
        }

        $this->pdo->beginTransaction();
    }

    /**
     * @throws ConnectionException
     */
    public function commit(): void
    {
        $this->connect();

        $this->pdo->commit();
    }

    /**
     * @throws ConnectionException
     */
    public function rollback(): void
    {
        $this->connect();

        $this->pdo->rollBack();
    }

    /**
     * @throws ConnectionException
     */
    public function inTransaction(): bool
    {
        $this->connect();

        return $this->pdo->inTransaction();
    }

    /**
     * @throws Throwable
     */
    public function transaction(
        callable $callback,
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
}
