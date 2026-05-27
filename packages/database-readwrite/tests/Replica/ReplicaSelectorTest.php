<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\ReadWrite\Replica\RandomReplicaSelector;
use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;
use Marko\Database\ReadWrite\Replica\WeightedReplicaSelector;

describe('ReplicaSelectorInterface', function (): void {
    it('defines ReplicaSelectorInterface with a select method', function (): void {
        $reflection = new ReflectionClass(ReplicaSelectorInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('select'))->toBeTrue();

        $select = $reflection->getMethod('select');

        expect($select->getReturnType()?->getName())->toBe(ConnectionInterface::class);

        $params = $select->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('replicas')
            ->and($params[0]->getType()?->getName())->toBe('array');
    });
});

describe('RandomReplicaSelector', function (): void {
    it('selects a replica from the list using RandomReplicaSelector', function (): void {
        $replica1 = createFakeConnection();
        $replica2 = createFakeConnection();
        $replica3 = createFakeConnection();

        $selector = new RandomReplicaSelector();
        $selected = $selector->select([$replica1, $replica2, $replica3]);

        expect(in_array($selected, [$replica1, $replica2, $replica3], true))->toBeTrue();
    });

    it('always returns a replica from the provided list in RandomReplicaSelector', function (): void {
        $replicas = array_map(fn () => createFakeConnection(), range(1, 5));
        $selector = new RandomReplicaSelector();

        foreach (range(1, 100) as $_) {
            $selected = $selector->select($replicas);
            expect(in_array($selected, $replicas, true))->toBeTrue();
        }
    });

    it('returns the only replica when one replica is provided in RandomReplicaSelector', function (): void {
        $replica = createFakeConnection();
        $selector = new RandomReplicaSelector();

        $selected = $selector->select([$replica]);

        expect($selected)->toBe($replica);
    });
});

describe('WeightedReplicaSelector', function (): void {
    it('selects replicas proportionally by weight in WeightedReplicaSelector', function (): void {
        $replica1 = createFakeConnection();
        $replica2 = createFakeConnection();

        // replica1 has weight 3, replica2 has weight 1 — expect ~75% vs ~25%
        $selector = new WeightedReplicaSelector([3, 1]);

        $counts = [0, 0];
        $iterations = 1000;

        foreach (range(1, $iterations) as $_) {
            $selected = $selector->select([$replica1, $replica2]);
            if ($selected === $replica1) {
                $counts[0]++;
            } else {
                $counts[1]++;
            }
        }

        // With 3:1 weight ratio, replica1 should be selected ~75% of the time
        // Allow generous tolerance: expect at least 60% and at most 90% for replica1
        $ratio1 = $counts[0] / $iterations;

        expect($ratio1)->toBeGreaterThan(0.60)
            ->and($ratio1)->toBeLessThan(0.90)
            ->and($counts[0])->toBeGreaterThan($counts[1]);
    });

    it('returns the only replica when one replica is provided in WeightedReplicaSelector', function (): void {
        $replica = createFakeConnection();
        $selector = new WeightedReplicaSelector([1]);

        $selected = $selector->select([$replica]);

        expect($selected)->toBe($replica);
    });
});

function createFakeConnection(): ConnectionInterface
{
    return new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            return 0;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };
}
