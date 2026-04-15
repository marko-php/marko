<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Marko\Database\Repository\RepositoryQueryBuilder;

// ── Entity Fixtures ────────────────────────────────────────────────────────────

#[Table('rqb_users')]
class RqbUser extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';

    #[HasOne(RqbProfile::class, foreignKey: 'userId')]
    public ?RqbProfile $profile = null;

    #[HasMany(RqbPost::class, foreignKey: 'userId')]
    /** @var RqbPost[] */
    public array $posts = [];
}

#[Table('rqb_profiles')]
class RqbProfile extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $userId = 0;
}

#[Table('rqb_posts')]
class RqbPost extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $userId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $title = '';
}

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * @param array<array<string, mixed>> $rows
 */
function makeRqbStubBuilder(array $rows = []): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
        /** @var array<string> */
        public array $wheresCalled = [];

        /** @var array<string> */
        public array $orderByCalled = [];

        public function __construct(private readonly array $rows) {}

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function where(string $column, string $operator, mixed $value): static
        {
            $this->wheresCalled[] = "$column $operator $value";

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
            $this->orderByCalled[] = "$column $direction";

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

function makeRqbLoader(array $relatedRows = []): RelationshipLoader
{
    $stubBuilder = makeRqbStubBuilder($relatedRows);

    $factory = new class ($stubBuilder) implements QueryBuilderFactoryInterface
    {
        public function __construct(private readonly QueryBuilderInterface $builder) {}

        public function create(): QueryBuilderInterface
        {
            return $this->builder;
        }
    };

    return new RelationshipLoader(
        new EntityMetadataFactory(),
        new EntityHydrator(),
        $factory,
    );
}

function makeRqbMetadata(): EntityMetadata
{
    return (new EntityMetadataFactory())->parse(RqbUser::class);
}

function makeRqb(
    QueryBuilderInterface $stubBuilder,
    ?RelationshipLoader $loader = null,
): RepositoryQueryBuilder {
    return new RepositoryQueryBuilder(
        queryBuilder: $stubBuilder,
        hydrator: new EntityHydrator(),
        metadata: makeRqbMetadata(),
        entityClass: RqbUser::class,
        relationshipLoader: $loader,
    );
}

// ── with() on Query Builder ─────────────────────────────────────────────────────

it('accepts relationship names via with', function (): void {
    $stub = makeRqbStubBuilder();
    $rqb = makeRqb($stub);

    $result = $rqb->with('profile');

    expect($result)->toBeInstanceOf(RepositoryQueryBuilder::class);
});

it('loads relationships on getEntities results', function (): void {
    $rows = [['id' => 1, 'name' => 'Alice']];
    $profileRows = [['id' => 10, 'user_id' => 1]];
    $stub = makeRqbStubBuilder($rows);
    $loader = makeRqbLoader($profileRows);
    $rqb = makeRqb($stub, $loader);

    $collection = $rqb->with('profile')->getEntities();
    $entities = $collection->toArray();

    expect($entities)->toHaveCount(1)
        ->and($entities[0]->profile)->toBeInstanceOf(RqbProfile::class);
});

it('loads relationships on firstEntity result', function (): void {
    $rows = [['id' => 1, 'name' => 'Alice']];
    $profileRows = [['id' => 10, 'user_id' => 1]];
    $stub = makeRqbStubBuilder($rows);
    $loader = makeRqbLoader($profileRows);
    $rqb = makeRqb($stub, $loader);

    $entity = $rqb->with('profile')->firstEntity();

    expect($entity)->toBeInstanceOf(RqbUser::class)
        ->and($entity->profile)->toBeInstanceOf(RqbProfile::class);
});

it('returns null from firstEntity without loading when no result', function (): void {
    $stub = makeRqbStubBuilder([]);
    $loader = makeRqbLoader();
    $rqb = makeRqb($stub, $loader);

    $entity = $rqb->with('profile')->firstEntity();

    expect($entity)->toBeNull();
});

it('chains with and where clauses fluently', function (): void {
    $stub = makeRqbStubBuilder([]);
    $rqb = makeRqb($stub);

    $result = $rqb->with('profile')->where('name', '=', 'Alice');

    expect($result)->toBeInstanceOf(RepositoryQueryBuilder::class)
        ->and($stub->wheresCalled)->toBe(['name = Alice']);
});

// ── matching() on Query Builder ─────────────────────────────────────────────────

it('applies single specification via matching', function (): void {
    $rows = [['id' => 1, 'name' => 'Alice']];
    $stub = makeRqbStubBuilder($rows);
    $rqb = makeRqb($stub);

    $activeSpec = new class () implements QuerySpecification
    {
        public function apply(QueryBuilderInterface $builder): void
        {
            $builder->where('name', '=', 'Alice');
        }
    };

    $collection = $rqb->matching($activeSpec);

    expect($collection)->toBeInstanceOf(EntityCollection::class)
        ->and($collection->count())->toBe(1)
        ->and($stub->wheresCalled)->toBe(['name = Alice']);
});

it('applies multiple specifications via matching', function (): void {
    $rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];
    $stub = makeRqbStubBuilder($rows);
    $rqb = makeRqb($stub);

    $firstSpec = new class () implements QuerySpecification
    {
        public function apply(QueryBuilderInterface $builder): void
        {
            $builder->where('name', '!=', 'Charlie');
        }
    };

    $secondSpec = new class () implements QuerySpecification
    {
        public function apply(QueryBuilderInterface $builder): void
        {
            $builder->where('id', '>', 0);
        }
    };

    $collection = $rqb->matching($firstSpec, $secondSpec);

    expect($collection)->toBeInstanceOf(EntityCollection::class)
        ->and($collection->count())->toBe(2)
        ->and($stub->wheresCalled)->toBe(['name != Charlie', 'id > 0']);
});

it('chains matching with where and orderBy', function (): void {
    $stub = makeRqbStubBuilder([]);
    $rqb = makeRqb($stub);

    $spec = new class () implements QuerySpecification
    {
        public function apply(QueryBuilderInterface $builder): void
        {
            $builder->where('name', '=', 'Alice');
        }
    };

    $rqb->where('id', '>', 0)->orderBy('name', 'ASC')->matching($spec);

    expect($stub->wheresCalled)->toBe(['id > 0', 'name = Alice'])
        ->and($stub->orderByCalled)->toBe(['name ASC']);
});

it('chains matching with with for relationships and specifications together', function (): void {
    $rows = [['id' => 1, 'name' => 'Alice']];
    $profileRows = [['id' => 10, 'user_id' => 1]];
    $stub = makeRqbStubBuilder($rows);
    $loader = makeRqbLoader($profileRows);
    $rqb = makeRqb($stub, $loader);

    $spec = new class () implements QuerySpecification
    {
        public function apply(QueryBuilderInterface $builder): void
        {
            $builder->where('name', '=', 'Alice');
        }
    };

    $collection = $rqb->with('profile')->matching($spec);

    expect($collection)->toBeInstanceOf(EntityCollection::class)
        ->and($collection->count())->toBe(1)
        ->and($collection->toArray()[0]->profile)->toBeInstanceOf(RqbProfile::class);
});

// ── getEntities Return Type ─────────────────────────────────────────────────────

it('returns EntityCollection from getEntities', function (): void {
    $rows = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ];
    $stub = makeRqbStubBuilder($rows);
    $rqb = makeRqb($stub);

    $result = $rqb->getEntities();

    expect($result)->toBeInstanceOf(EntityCollection::class)
        ->and($result->count())->toBe(2);
});

it('returns empty EntityCollection when no results', function (): void {
    $stub = makeRqbStubBuilder([]);
    $rqb = makeRqb($stub);

    $result = $rqb->getEntities();

    expect($result)->toBeInstanceOf(EntityCollection::class)
        ->and($result->isEmpty())->toBeTrue();
});
