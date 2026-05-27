<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Replica;

use Marko\Database\Connection\ConnectionInterface;

interface ReplicaSelectorInterface
{
    /**
     * Select one replica from the given list.
     *
     * @param ConnectionInterface[] $replicas
     */
    public function select(array $replicas): ConnectionInterface;
}
