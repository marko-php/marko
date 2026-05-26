<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Replica;

use Marko\Database\Connection\ConnectionInterface;

readonly class RandomReplicaSelector implements ReplicaSelectorInterface
{
    public function select(array $replicas): ConnectionInterface
    {
        return $replicas[array_rand($replicas)];
    }
}
