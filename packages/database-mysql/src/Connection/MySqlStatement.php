<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Connection;

use Marko\Database\Connection\StatementInterface;
use PDO;
use PDOStatement;

class MySqlStatement implements StatementInterface
{
    public function __construct(
        private readonly PDOStatement $statement,
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
        $result = $this->statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
