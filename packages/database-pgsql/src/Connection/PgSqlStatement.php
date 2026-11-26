<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Closure;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;
use PDO;
use PDOException;
use PDOStatement;

readonly class PgSqlStatement implements StatementInterface
{
    public function __construct(
        private PDOStatement $statement,
        private PgSqlExceptionTranslator $exceptionTranslator = new PgSqlExceptionTranslator(),
        private ?Closure $connectionLost = null,
    ) {}

    /**
     * @throws QueryException
     */
    public function execute(
        array $bindings = [],
    ): bool {
        try {
            return $this->statement->execute($bindings);
        } catch (PDOException $e) {
            $translated = $this->exceptionTranslator->translate($e, $this->statement->queryString, $bindings);

            if (
                $translated instanceof LostConnectionException
                && $this->connectionLost !== null
            ) {
                ($this->connectionLost)();
            }

            throw $translated;
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
