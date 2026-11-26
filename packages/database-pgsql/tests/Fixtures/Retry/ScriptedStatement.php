<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Retry;

use PDOStatement;

/**
 * The statement class of ScriptedPdo: execute() throws the failures scripted
 * on the PDO that prepared it, in order.
 */
class ScriptedStatement extends PDOStatement
{
    protected function __construct(
        private readonly ScriptedPdo $pdo,
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
