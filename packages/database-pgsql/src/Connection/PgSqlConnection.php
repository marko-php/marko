<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Exceptions\ConnectionException;
use PDO;
use PDOException;
use Throwable;

class PgSqlConnection implements ConnectionInterface, TransactionInterface
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $charset = 'utf8',
    ) {}

    public function connect(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = new PDO(
                $this->buildDsn(),
                $this->username,
                $this->password,
                $this->getPdoOptions(),
            );

            $this->pdo->exec($this->getSetEncodingQuery());
        } catch (PDOException $e) {
            throw ConnectionException::connectionFailed(
                $this->host,
                $this->port,
                $this->database,
                $e,
            );
        }
    }

    private function buildDsn(): string
    {
        return "pgsql:host=$this->host;port=$this->port;dbname=$this->database";
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

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    private function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->ensureConnected();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->ensureConnected();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        $this->ensureConnected();

        $pdoStatement = $this->pdo->prepare($sql);

        return new PgSqlStatement($pdoStatement);
    }

    public function lastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->ensureConnected();

        if ($this->pdo->inTransaction()) {
            throw TransactionException::nestedTransactionNotSupported();
        }

        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->ensureConnected();

        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->ensureConnected();

        $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        $this->ensureConnected();

        return $this->pdo->inTransaction();
    }

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
