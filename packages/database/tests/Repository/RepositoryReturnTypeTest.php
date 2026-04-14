<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Database\Repository\RepositoryInterface;
use ReflectionClass;
use RuntimeException;

#[Table('items')]
class ReturnTypeTestItem extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;
}

class ReturnTypeItemRepository extends Repository
{
    protected const string ENTITY_CLASS = ReturnTypeTestItem::class;
}

/**
 * @param array<array<string, mixed>> $queryResult
 */
function createReturnTypeMockConnection(array $queryResult = []): ConnectionInterface
{
    return new readonly class ($queryResult) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return $this->queryResult;
        }

        public function execute(string $sql, array $bindings = []): int
        {
            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}

it('declares findAll return type as EntityCollection', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);
    $method = $reflection->getMethod('findAll');
    $returnType = $method->getReturnType();

    expect($returnType->getName())->toBe(EntityCollection::class);
});

it('declares findBy return type as EntityCollection', function (): void {
    $reflection = new ReflectionClass(RepositoryInterface::class);
    $method = $reflection->getMethod('findBy');
    $returnType = $method->getReturnType();

    expect($returnType->getName())->toBe(EntityCollection::class);
});

it('returns EntityCollection from findAll', function (): void {
    $connection = createReturnTypeMockConnection([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findAll();

    expect($result)->toBeInstanceOf(EntityCollection::class);
});

it('returns empty EntityCollection from findAll when no entities exist', function (): void {
    $connection = createReturnTypeMockConnection([]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findAll();

    expect($result)->toBeInstanceOf(EntityCollection::class)
        ->and($result->isEmpty())->toBeTrue();
});

it('returns EntityCollection from findBy', function (): void {
    $connection = createReturnTypeMockConnection([
        ['id' => 1, 'name' => 'Alpha'],
    ]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findBy(['name' => 'Alpha']);

    expect($result)->toBeInstanceOf(EntityCollection::class);
});

it('returns EntityCollection that provides toArray for array access', function (): void {
    $connection = createReturnTypeMockConnection([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findAll();
    $array = $result->toArray();

    expect($array)->toBeArray()
        ->and($array[0])->toBeInstanceOf(ReturnTypeTestItem::class)
        ->and($array[0]->name)->toBe('Alpha')
        ->and($array[1]->name)->toBe('Beta');
});

it('returns EntityCollection that is countable', function (): void {
    $connection = createReturnTypeMockConnection([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
        ['id' => 3, 'name' => 'Gamma'],
    ]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findAll();

    expect(count($result))->toBe(3);
});

it('returns EntityCollection that is iterable with foreach', function (): void {
    $connection = createReturnTypeMockConnection([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findAll();
    $names = [];

    foreach ($result as $item) {
        $names[] = $item->name;
    }

    expect($names)->toBe(['Alpha', 'Beta']);
});

it('returns empty EntityCollection from findBy when no matches', function (): void {
    $connection = createReturnTypeMockConnection([]);
    $repository = new ReturnTypeItemRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $result = $repository->findBy(['name' => 'NonExistent']);

    expect($result)->toBeInstanceOf(EntityCollection::class)
        ->and($result->isEmpty())->toBeTrue();
});
