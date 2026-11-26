<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\LostConnection;

use PDOStatement;

/**
 * The statement class of LossyPdo: execute() throws the failures scripted on its PDO.
 */
class LossyStatement extends PDOStatement
{
    protected function __construct(
        private readonly LossyPdo $pdo,
    ) {}

    public function execute(
        ?array $params = null,
    ): bool {
        $failure = array_shift($this->pdo->executeFailures);

        if ($failure !== null) {
            throw $failure;
        }

        return parent::execute($params);
    }
}
