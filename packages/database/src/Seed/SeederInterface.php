<?php

declare(strict_types=1);

namespace Marko\Database\Seed;

use Marko\Database\Connection\ConnectionInterface;

interface SeederInterface
{
    public function run(
        ConnectionInterface $connection,
    ): void;
}
