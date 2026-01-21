<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Connection;

use Marko\Database\Connection\StatementInterface;
use PDO;
use PDOStatement;

readonly class PgSqlStatement implements StatementInterface
{
    public function __construct(
        private PDOStatement $statement,
    ) {}

    public function execute(
        array $bindings = [],
    ): bool {
        return $this->statement->execute($bindings);
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
