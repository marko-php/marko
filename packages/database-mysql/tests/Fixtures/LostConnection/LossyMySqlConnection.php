<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\LostConnection;

use Closure;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\MySql\Connection\MySqlConnection;
use PDO;

/**
 * A MySqlConnection that hands out a fresh LossyPdo per createPdo() call, recording them in creation order.
 * $scripts[n] runs on the n-th PDO as it is created.
 */
class LossyMySqlConnection extends MySqlConnection
{
    /**
     * @var list<LossyPdo>
     */
    public array $pdos = [];

    /**
     * @param array<int, Closure(LossyPdo): void> $scripts
     */
    public function __construct(
        DatabaseConfig $config,
        private readonly array $scripts = [],
    ) {
        parent::__construct($config);
    }

    protected function createPdo(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        $pdo = new LossyPdo();

        if (isset($this->scripts[count($this->pdos)])) {
            $this->scripts[count($this->pdos)]($pdo);
        }

        $this->pdos[] = $pdo;

        return $pdo;
    }
}
