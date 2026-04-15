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
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Marko\Database\Repository\Repository;
use RuntimeException;

#[Table('products')]
class MatchingTestProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column]
    public string $status;

    #[Column]
    public int $price;
}

class ProductRepository extends Repository
{
    protected const string ENTITY_CLASS = MatchingTestProduct::class;
}

/**
 * Create a stub QueryBuilderInterface that returns given rows from get().
 *
 * @param array<array<string, mixed>> $rows
 */
function makeStubBuilder(array $rows = []): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
        /** @var array<string> */
        public array $wheresCalled = [];

        public function __construct(private readonly array $rows) {}

        public function where(string $column, string $operator, mixed $value): static
        {
            $this->wheresCalled[] = "$column $operator $value";

            return $this;
        }

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function whereIn(string $column, array $values): static
        {
            return $this;
        }

        public function whereNull(string $column): static
        {
            return $this;
        }

        public function whereNotNull(string $column): static
        {
            return $this;
        }

        public function orWhere(string $column, string $operator, mixed $value): static
        {
            return $this;
        }

        public function join(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function leftJoin(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function rightJoin(string $table, string $first, string $operator, string $second): static
        {
            return $this;
        }

        public function orderBy(string $column, string $direction = 'ASC'): static
        {
            return $this;
        }

        public function limit(int $limit): static
        {
            return $this;
        }

        public function offset(int $offset): static
        {
            return $this;
        }

        public function get(): array
        {
            return $this->rows;
        }

        public function first(): ?array
        {
            return $this->rows[0] ?? null;
        }

        public function insert(array $data): int
        {
            return 0;
        }

        public function update(array $data): int
        {
            return 0;
        }

        public function delete(): int
        {
            return 0;
        }

        public function count(): int
        {
            return count($this->rows);
        }

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }
    };
}

/**
 * Create a repository with an injected stub query builder.
 */
function makeRepository(QueryBuilderInterface $stubBuilder): ProductRepository
{
    $connection = new class () implements ConnectionInterface
    {
        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return [];
        }

        public function execute(string $sql, array $bindings = []): int
        {
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

    $factory = new class ($stubBuilder) implements QueryBuilderFactoryInterface
    {
        public function __construct(private readonly QueryBuilderInterface $builder) {}

        public function create(): QueryBuilderInterface
        {
            return $this->builder;
        }
    };

    return new ProductRepository(
        connection: $connection,
        metadataFactory: new EntityMetadataFactory(),
        hydrator: new EntityHydrator(),
        queryBuilderFactory: $factory,
    );
}

describe('Repository matching()', function (): void {
    it('returns EntityCollection from matching with single specification', function (): void {
        $rows = [
            ['id' => 1, 'name' => 'Widget', 'status' => 'active', 'price' => 100],
        ];
        $stub = makeStubBuilder($rows);
        $repo = makeRepository($stub);

        $activeSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', 'active');
            }
        };

        $result = $repo->matching($activeSpec);

        expect($result)->toBeInstanceOf(EntityCollection::class)
            ->and($result->count())->toBe(1);
    });

    it('returns EntityCollection from matching with multiple specifications', function (): void {
        $rows = [
            ['id' => 1, 'name' => 'Widget', 'status' => 'active', 'price' => 100],
            ['id' => 2, 'name' => 'Gadget', 'status' => 'active', 'price' => 200],
        ];
        $stub = makeStubBuilder($rows);
        $repo = makeRepository($stub);

        $activeSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', 'active');
            }
        };

        $cheapSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('price', '<', 300);
            }
        };

        $result = $repo->matching($activeSpec, $cheapSpec);

        expect($result)->toBeInstanceOf(EntityCollection::class)
            ->and($result->count())->toBe(2);
    });

    it('applies specifications in order to query builder', function (): void {
        $stub = makeStubBuilder([]);
        $repo = makeRepository($stub);

        $firstSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', 'active');
            }
        };

        $secondSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('price', '>', 50);
            }
        };

        $repo->matching($firstSpec, $secondSpec);

        expect($stub->wheresCalled)->toBe(['status = active', 'price > 50']);
    });

    it('returns empty collection when no entities match specifications', function (): void {
        $stub = makeStubBuilder([]);
        $repo = makeRepository($stub);

        $spec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', 'deleted');
            }
        };

        $result = $repo->matching($spec);

        expect($result)->toBeInstanceOf(EntityCollection::class)
            ->and($result->isEmpty())->toBeTrue()
            ->and($result->count())->toBe(0);
    });

    it('throws RepositoryException when query builder is not configured', function (): void {
        $connection = new class () implements ConnectionInterface
        {
            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(string $sql, array $bindings = []): array
            {
                return [];
            }

            public function execute(string $sql, array $bindings = []): int
            {
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

        $repo = new ProductRepository(
            connection: $connection,
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
        );

        $spec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void {}
        };

        expect(fn () => $repo->matching($spec))->toThrow(RepositoryException::class);
    });
});

describe('Specification Composition', function (): void {
    it('composes two specifications filtering results cumulatively', function (): void {
        $rows = [
            ['id' => 1, 'name' => 'Widget', 'status' => 'active', 'price' => 100],
        ];
        $stub = makeStubBuilder($rows);
        $repo = makeRepository($stub);

        $activeSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', 'active');
            }
        };

        $affordableSpec = new class () implements QuerySpecification
        {
            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('price', '<', 200);
            }
        };

        $result = $repo->matching($activeSpec, $affordableSpec);

        expect($result->count())->toBe(1)
            ->and($stub->wheresCalled)->toBe(['status = active', 'price < 200']);
    });

    it('allows specifications with constructor parameters', function (): void {
        $rows = [
            ['id' => 3, 'name' => 'Premium', 'status' => 'active', 'price' => 999],
        ];
        $stub = makeStubBuilder($rows);
        $repo = makeRepository($stub);

        $statusSpec = new class ('active') implements QuerySpecification
        {
            public function __construct(private readonly string $status) {}

            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('status', '=', $this->status);
            }
        };

        $minPriceSpec = new class (500) implements QuerySpecification
        {
            public function __construct(private readonly int $minPrice) {}

            public function apply(QueryBuilderInterface $builder): void
            {
                $builder->where('price', '>=', $this->minPrice);
            }
        };

        $result = $repo->matching($statusSpec, $minPriceSpec);

        expect($result->count())->toBe(1)
            ->and($stub->wheresCalled)->toBe(['status = active', 'price >= 500']);
    });
});
