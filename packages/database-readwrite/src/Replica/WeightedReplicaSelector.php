<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Replica;

use Marko\Database\Connection\ConnectionInterface;
use Random\RandomException;

readonly class WeightedReplicaSelector implements ReplicaSelectorInterface
{
    /** @param int[] $weights Parallel to the replicas array passed to select() */
    public function __construct(private array $weights) {}

    /**
     * @throws RandomException
     */
    public function select(array $replicas): ConnectionInterface
    {
        $total = array_sum($this->weights);
        $rand = random_int(1, $total);
        $cumulative = 0;

        foreach ($this->weights as $index => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $replicas[$index];
            }
        }

        // Fallback (should not be reached if weights are valid)
        return $replicas[count($replicas) - 1];
    }
}
