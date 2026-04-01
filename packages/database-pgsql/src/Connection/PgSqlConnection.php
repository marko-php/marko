<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\PgSql\Exceptions\ConnectionException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

class PgSqlConnection implements ConnectionInterface, TransactionInterface
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly DatabaseConfig $config,
        private readonly string $charset = 'utf8',
    ) {}

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

        try {
            $this->pdo = $this->createPdo(
                $this->buildDsn(),
                $this->config->username,
                $this->config->password,
                $this->getPdoOptions(),
            );

            $this->pdo->exec($this->getSetEncodingQuery());
        } catch (PDOException $e) {
            throw ConnectionException::connectionFailed(
                $this->config->host,
                $this->config->port,
                $this->config->database,
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
    private function ensureConnected(): void
    {
        if ($this->pdo === null) {
            $this->connect();
        }
    }

    /**
     * @throws ConnectionException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->ensureConnected();

        $statement = $this->pdo->prepare($sql);
        $this->bindValues($statement, $bindings);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @throws ConnectionException
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->ensureConnected();

        $statement = $this->pdo->prepare($sql);
        $this->bindValues($statement, $bindings);
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * @throws ConnectionException
     */
    public function prepare(
        string $sql,
    ): StatementInterface {
        $this->ensureConnected();

        $pdoStatement = $this->pdo->prepare($sql);

        return new PgSqlStatement($pdoStatement);
    }

    /**
     * @param array<int|string, mixed> $bindings
     */
    private function bindValues(
        PDOStatement $statement, 
        array $bindings,
    ): void {
        foreach ($bindings as $key => $value) {
            $param = is_int($key) ? $key + 1 : $key;
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
     * @throws ConnectionException
     */
    public function lastInsertId(): int
    {
        $this->ensureConnected();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @throws TransactionException|ConnectionException
     */
    public function beginTransaction(): void
    {
        $this->ensureConnected();

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
        $this->ensureConnected();

        $this->pdo->commit();
    }

    /**
     * @throws ConnectionException
     */
    public function rollback(): void
    {
        $this->ensureConnected();

        $this->pdo->rollBack();
    }

    /**
     * @throws ConnectionException
     */
    public function inTransaction(): bool
    {
        $this->ensureConnected();

        return $this->pdo->inTransaction();
    }

    /**
     * @throws ConnectionException|Throwable|TransactionException
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
