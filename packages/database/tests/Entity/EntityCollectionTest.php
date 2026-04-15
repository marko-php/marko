<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Countable;
use IteratorAggregate;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;

// Fixture entity for tests
function makeEntity(int $id, string $name, ?string $role = null): Entity
{
    $entity = new class () extends Entity
    {
        public int $id;

        public string $name;

        public ?string $role = null;
    };
    $entity->id = $id;
    $entity->name = $name;
    $entity->role = $role;

    return $entity;
}

describe('Construction & Basic Access', function (): void {
    it('creates an empty collection', function (): void {
        $collection = new EntityCollection([]);

        expect($collection->toArray())->toBeEmpty();
    });

    it('creates a collection from an array of entities', function (): void {
        $entity = makeEntity(1, 'Alice');
        $collection = new EntityCollection([$entity]);

        expect($collection->toArray())->toBe([$entity]);
    });

    it('returns the count of entities', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);

        expect($collection->count())->toBe(2);
    });

    it('returns true for isEmpty when collection has no entities', function (): void {
        $collection = new EntityCollection([]);

        expect($collection->isEmpty())->toBeTrue();
    });

    it('returns false for isEmpty when collection has entities', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice')]);

        expect($collection->isEmpty())->toBeFalse();
    });

    it('returns all entities as an array via toArray', function (): void {
        $entities = [makeEntity(1, 'Alice'), makeEntity(2, 'Bob')];
        $collection = new EntityCollection($entities);

        expect($collection->toArray())->toBe($entities);
    });

    it('is iterable via foreach', function (): void {
        $entities = [makeEntity(1, 'Alice'), makeEntity(2, 'Bob')];
        $collection = new EntityCollection($entities);

        $iterated = [];
        foreach ($collection as $entity) {
            $iterated[] = $entity;
        }

        expect($iterated)->toBe($entities);
    });

    it('implements Countable interface', function (): void {
        $collection = new EntityCollection([]);

        expect($collection)->toBeInstanceOf(Countable::class);
    });

    it('implements IteratorAggregate interface', function (): void {
        $collection = new EntityCollection([]);

        expect($collection)->toBeInstanceOf(IteratorAggregate::class);
    });
});

describe('Retrieval Methods', function (): void {
    it('returns the first entity or null when empty', function (): void {
        $collection = new EntityCollection([]);

        expect($collection->first())->toBeNull();
    });

    it('returns the last entity or null when empty', function (): void {
        $collection = new EntityCollection([]);

        expect($collection->last())->toBeNull();
    });

    it('returns the first entity from a non-empty collection', function (): void {
        $first = makeEntity(1, 'Alice');
        $collection = new EntityCollection([$first, makeEntity(2, 'Bob')]);

        expect($collection->first())->toBe($first);
    });

    it('returns the last entity from a non-empty collection', function (): void {
        $last = makeEntity(2, 'Bob');
        $collection = new EntityCollection([makeEntity(1, 'Alice'), $last]);

        expect($collection->last())->toBe($last);
    });
});

describe('Filtering & Transformation', function (): void {
    it('filters entities by callback returning new collection', function (): void {
        $alice = makeEntity(1, 'Alice');
        $bob = makeEntity(2, 'Bob');
        $collection = new EntityCollection([$alice, $bob]);

        $filtered = $collection->filter(fn (Entity $e): bool => $e->name === 'Alice'); // @phpstan-ignore-line

        expect($filtered->toArray())->toBe([$alice])
            ->and($filtered)->not->toBe($collection);
    });

    it('maps entities to an array of transformed values', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);

        $names = $collection->map(fn (Entity $e): string => $e->name); // @phpstan-ignore-line

        expect($names)->toBe(['Alice', 'Bob']);
    });

    it('applies each callback to every entity and returns self for chaining', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);
        $seen = [];

        $result = $collection->each(function (Entity $e) use (&$seen): void {
            $seen[] = $e->name; // @phpstan-ignore-line
        });

        expect($seen)->toBe(['Alice', 'Bob'])
            ->and($result)->toBe($collection);
    });

    it('checks contains with callback returning true when match exists', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);

        expect($collection->contains(fn (Entity $e): bool => $e->name === 'Bob'))->toBeTrue(); // @phpstan-ignore-line
    });

    it('checks contains with callback returning false when no match', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);

        expect($collection->contains(fn (Entity $e): bool => $e->name === 'Charlie'))->toBeFalse(); // @phpstan-ignore-line
    });
});

describe('Property Extraction', function (): void {
    it('plucks a single property from all entities into an array', function (): void {
        $collection = new EntityCollection([makeEntity(1, 'Alice'), makeEntity(2, 'Bob')]);

        expect($collection->pluck('name'))->toBe(['Alice', 'Bob']);
    });

    it('plucks a property that may be null', function (): void {
        $collection = new EntityCollection([
            makeEntity(1, 'Alice', 'admin'),
            makeEntity(2, 'Bob'),
        ]);

        expect($collection->pluck('role'))->toBe(['admin', null]);
    });
});

describe('Sorting', function (): void {
    it('sorts entities by a property in ascending order', function (): void {
        $alice = makeEntity(2, 'Alice');
        $bob = makeEntity(1, 'Bob');
        $collection = new EntityCollection([$alice, $bob]);

        $sorted = $collection->sortBy('id');

        expect($sorted->pluck('id'))->toBe([1, 2]);
    });

    it('sorts entities by a property in descending order', function (): void {
        $alice = makeEntity(1, 'Alice');
        $bob = makeEntity(2, 'Bob');
        $collection = new EntityCollection([$alice, $bob]);

        $sorted = $collection->sortBy('id', descending: true);

        expect($sorted->pluck('id'))->toBe([2, 1]);
    });

    it('returns a new collection when sorting without modifying original', function (): void {
        $alice = makeEntity(2, 'Alice');
        $bob = makeEntity(1, 'Bob');
        $collection = new EntityCollection([$alice, $bob]);

        $sorted = $collection->sortBy('id');

        expect($sorted)->not->toBe($collection)
            ->and($collection->pluck('id'))->toBe([2, 1]);
    });
});

describe('Grouping & Chunking', function (): void {
    it('groups entities by a property returning collection of collections', function (): void {
        $alice = makeEntity(1, 'Alice', 'admin');
        $bob = makeEntity(2, 'Bob', 'user');
        $charlie = makeEntity(3, 'Charlie', 'admin');
        $collection = new EntityCollection([$alice, $bob, $charlie]);

        $grouped = $collection->groupBy('role');

        expect($grouped)->toBeArray()
            ->and(array_keys($grouped))->toBe(['admin', 'user'])
            ->and($grouped['admin']->toArray())->toBe([$alice, $charlie])
            ->and($grouped['user']->toArray())->toBe([$bob]);
    });

    it('chunks entities into collection of collections of given size', function (): void {
        $entities = [makeEntity(1, 'A'), makeEntity(2, 'B'), makeEntity(3, 'C'), makeEntity(4, 'D')];
        $collection = new EntityCollection($entities);

        $chunks = $collection->chunk(2);

        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->toArray())->toBe([$entities[0], $entities[1]])
            ->and($chunks[1]->toArray())->toBe([$entities[2], $entities[3]]);
    });

    it('handles chunk size larger than collection', function (): void {
        $entities = [makeEntity(1, 'A'), makeEntity(2, 'B')];
        $collection = new EntityCollection($entities);

        $chunks = $collection->chunk(10);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->toArray())->toBe($entities);
    });
});
