<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T of Entity
 * @implements IteratorAggregate<int, T>
 */
class EntityCollection implements Countable, IteratorAggregate
{
    /**
     * @param array<int, T> $entities
     */
    public function __construct(
        private readonly array $entities = [],
    ) {}

    public function count(): int
    {
        return count($this->entities);
    }

    public function isEmpty(): bool
    {
        return $this->entities === [];
    }

    /**
     * @return array<int, T>
     */
    public function toArray(): array
    {
        return $this->entities;
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entities);
    }

    /**
     * @return T|null
     */
    public function first(): ?Entity
    {
        return array_first($this->entities);
    }

    /**
     * @return T|null
     */
    public function last(): ?Entity
    {
        return array_last($this->entities);
    }

    /**
     * @param callable(T): bool $callback
     * @return self<T>
     */
    public function filter(callable $callback): self
    {
        return new self(array_values(array_filter($this->entities, $callback)));
    }

    /**
     * @template U
     * @param callable(T): U $callback
     * @return array<int, U>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->entities);
    }

    /**
     * @param callable(T): void $callback
     * @return self<T>
     */
    public function each(callable $callback): self
    {
        foreach ($this->entities as $entity) {
            $callback($entity);
        }

        return $this;
    }

    /**
     * @param callable(T): bool $callback
     */
    public function contains(callable $callback): bool
    {
        return array_any($this->entities, $callback);
    }

    /**
     * @return array<int, mixed>
     */
    public function pluck(string $property): array
    {
        return array_map(fn (Entity $entity): mixed => $entity->$property, $this->entities);
    }

    /**
     * @return self<T>
     */
    public function sortBy(string $property, bool $descending = false): self
    {
        $sorted = $this->entities;
        usort($sorted, function (Entity $a, Entity $b) use ($property, $descending): int {
            $result = $a->$property <=> $b->$property;

            return $descending ? -$result : $result;
        });

        return new self($sorted);
    }

    /**
     * @return array<string, self<T>>
     */
    public function groupBy(string $property): array
    {
        $groups = [];
        foreach ($this->entities as $entity) {
            $key = (string) $entity->$property;
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $entity;
        }

        return array_map(fn (array $group): self => new self($group), $groups);
    }

    /**
     * @return array<int, self<T>>
     */
    public function chunk(int $size): array
    {
        $chunks = array_chunk($this->entities, $size);

        return array_map(fn (array $chunk): self => new self($chunk), $chunks);
    }
}
