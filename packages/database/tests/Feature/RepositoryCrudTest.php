<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use RuntimeException;

// Test entity for CRUD operations

#[Table('crud_products')]
class CrudProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255)]
    public string $name;

    #[Column(type: 'DECIMAL')]
    public float $price;

    #[Column]
    public int $stock = 0;

    #[Column]
    public bool $isAvailable = true;
}

class ProductRepository extends Repository
{
    protected const string ENTITY_CLASS = CrudProduct::class;
}

describe('Repository CRUD Operations', function (): void {
    it('performs CRUD operations via repository', function (): void {
        $storage = [];
        $lastId = 0;
        $executedQueries = [];

        $connection = new class ($storage, $lastId, $executedQueries) implements ConnectionInterface
        {
            public function __construct(
                private array &$storage,
                private int &$lastId,
                private array &$queries,
            ) {}

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
                $this->queries[] = ['sql' => $sql, 'bindings' => $bindings, 'type' => 'query'];

                // Simulate SELECT by ID
                if (str_contains($sql, 'WHERE id = ?') && count($bindings) > 0) {
                    $id = $bindings[0];
                    foreach ($this->storage as $row) {
                        if ($row['id'] === $id) {
                            return [$row];
                        }
                    }

                    return [];
                }

                // Simulate SELECT ALL
                if (str_starts_with($sql, 'SELECT * FROM')) {
                    return array_values($this->storage);
                }

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                $this->queries[] = ['sql' => $sql, 'bindings' => $bindings, 'type' => 'execute'];

                // Simulate INSERT
                if (str_starts_with($sql, 'INSERT')) {
                    $this->lastId++;
                    $this->storage[$this->lastId] = [
                        'id' => $this->lastId,
                        'name' => $bindings[0] ?? '',
                        'price' => $bindings[1] ?? 0.0,
                        'stock' => $bindings[2] ?? 0,
                        'isAvailable' => $bindings[3] ?? true,
                    ];

                    return 1;
                }

                // Simulate UPDATE
                if (str_starts_with($sql, 'UPDATE')) {
                    $id = end($bindings);
                    if (isset($this->storage[$id])) {
                        // For dirty tracking, only specific fields are updated
                        $setClause = substr($sql, strpos($sql, 'SET') + 4);
                        $setClause = substr($setClause, 0, strpos($setClause, 'WHERE'));
                        if (str_contains($setClause, 'name')) {
                            $this->storage[$id]['name'] = $bindings[0];
                        }
                        if (str_contains($setClause, 'price')) {
                            $this->storage[$id]['price'] = $bindings[0];
                        }

                        return 1;
                    }

                    return 0;
                }

                // Simulate DELETE
                if (str_starts_with($sql, 'DELETE')) {
                    $id = $bindings[0] ?? null;
                    if ($id && isset($this->storage[$id])) {
                        unset($this->storage[$id]);

                        return 1;
                    }

                    return 0;
                }

                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return $this->lastId;
            }
        };

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

        // CREATE
        $product = new CrudProduct();
        $product->name = 'Test Product';
        $product->price = 99.99;
        $product->stock = 10;
        $product->isAvailable = true;

        expect($product->id)->toBeNull();

        $repository->save($product);

        expect($product->id)->toBe(1);

        // READ
        $found = $repository->find(1);

        expect($found)
            ->not->toBeNull()
            ->and($found->name)->toBe('Test Product')
            ->and($found->price)->toBe(99.99);

        // UPDATE (via dirty tracking)
        $found->name = 'Updated Product';
        $repository->save($found);

        $updated = $repository->find(1);
        expect($updated->name)->toBe('Updated Product');

        // DELETE
        $repository->delete($updated);

        $deleted = $repository->find(1);
        expect($deleted)->toBeNull();
    });

    it('finds entities by criteria', function (): void {
        $storage = [
            1 => ['id' => 1, 'name' => 'Active Product', 'price' => 10.0, 'stock' => 5, 'isAvailable' => true],
            2 => ['id' => 2, 'name' => 'Inactive Product', 'price' => 20.0, 'stock' => 0, 'isAvailable' => false],
            3 => ['id' => 3, 'name' => 'Another Active', 'price' => 15.0, 'stock' => 3, 'isAvailable' => true],
        ];

        $connection = new class ($storage) implements ConnectionInterface
        {
            public function __construct(
                private array $storage,
            ) {}

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
                // Simulate findBy with isAvailable criteria
                if (str_contains($sql, 'isAvailable = ?')) {
                    $searchValue = $bindings[0];

                    return array_values(array_filter(
                        $this->storage,
                        fn ($row) => $row['isAvailable'] === $searchValue,
                    ));
                }

                return array_values($this->storage);
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 1;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 1;
            }
        };

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

        $activeProducts = $repository->findBy(['isAvailable' => true]);

        expect($activeProducts)
            ->toHaveCount(2)
            ->and($activeProducts[0]->isAvailable)->toBeTrue()
            ->and($activeProducts[1]->isAvailable)->toBeTrue();
    });

    it('returns null for non-existent entities', function (): void {
        $connection = new class () implements ConnectionInterface
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

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

        $product = $repository->find(999);

        expect($product)->toBeNull();
    });

    it('counts entities correctly', function (): void {
        $connection = new class () implements ConnectionInterface
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
                if (str_contains($sql, 'COUNT(*)')) {
                    return [['aggregate' => 5]];
                }

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

        expect($repository->count())->toBe(5);
    });

    it('checks entity existence', function (): void {
        $storage = [
            1 => ['id' => 1, 'name' => 'Existing', 'price' => 10.0, 'stock' => 1, 'isAvailable' => true],
        ];

        $connection = new class ($storage) implements ConnectionInterface
        {
            public function __construct(
                private array $storage,
            ) {}

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
                if (str_contains($sql, 'WHERE id = ?')) {
                    $id = $bindings[0];

                    return isset($this->storage[$id]) ? [$this->storage[$id]] : [];
                }

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $metadataFactory = new EntityMetadataFactory();
        $hydrator = new EntityHydrator();
        $repository = new ProductRepository($connection, $metadataFactory, $hydrator);

        expect($repository->exists(1))
            ->toBeTrue()
            ->and($repository->exists(999))->toBeFalse();
    });
});
