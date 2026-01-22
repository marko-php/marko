<?php

declare(strict_types=1);

namespace Marko\Queue\Database;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Queue\QueueInterface;

class DatabaseQueueFactory
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function create(): QueueInterface
    {
        return new DatabaseQueue(
            connection: $this->connection,
        );
    }
}
