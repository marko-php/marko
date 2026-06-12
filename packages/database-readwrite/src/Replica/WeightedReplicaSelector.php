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
     * Selects a replica using weighted random selection over the first
     * count($replicas) weights. This ensures the returned index always
     * maps to a valid element even when the caller has removed replicas
     * during a fallback loop (which re-indexes via array_values).
     *
     * @throws RandomException
     */
    public function select(array $replicas): ConnectionInterface
    {
        $count = count($replicas);
        $activeWeights = array_slice($this->weights, 0, $count);
        $total = array_sum($activeWeights);
        $rand = random_int(1, max(1, $total));
        $cumulative = 0;

        foreach ($activeWeights as $index => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $replicas[$index];
            }
        }

        // Fallback: return last element (safe — $replicas is always non-empty here)
        return $replicas[$count - 1];
    }
}
