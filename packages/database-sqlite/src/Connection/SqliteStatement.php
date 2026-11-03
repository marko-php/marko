<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Connection;

use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;

readonly class SqliteStatement implements StatementInterface
{
    public function __construct(
        private PDOStatement $statement,
        private SqliteExceptionTranslator $exceptionTranslator = new SqliteExceptionTranslator(),
    ) {}

    /**
     * @param array<int|string, mixed> $bindings
     *
     * @throws QueryException
     */
    public function execute(
        array $bindings = [],
    ): bool {
        try {
            return $this->statement->execute($bindings);
        } catch (PDOException $e) {
            throw $this->exceptionTranslator->translate($e, $this->statement->queryString, $bindings);
        }
    }

    public function fetchAll(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetch(): ?array
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
