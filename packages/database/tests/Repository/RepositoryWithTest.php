<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Repository\Repository;
use RuntimeException;

// ── Entity Fixtures ────────────────────────────────────────────────────────────

#[Table('with_users')]
class WithUser extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';

    /** @noinspection PhpUnused */
    #[Column]
    public ?int $countryId = null;

    #[HasOne(WithProfile::class, foreignKey: 'userId')]
    public ?WithProfile $profile = null;

    #[HasMany(WithPost::class, foreignKey: 'userId')]
    /** @var WithPost[] */
    public array $posts = [];

    #[BelongsTo(WithCountry::class, foreignKey: 'countryId')]
    public ?WithCountry $country = null;
}

#[Table('with_profiles')]
class WithProfile extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $userId = 0;
}

#[Table('with_posts')]
class WithPost extends Entity
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

#[Table('with_countries')]
class WithCountry extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

// ── Repository Fixture ─────────────────────────────────────────────────────────

class WithUserRepository extends Repository
{
    protected const string ENTITY_CLASS = WithUser::class;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeWithStubBuilder(array $rows = []): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
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

function makeWithConnection(array $rows = []): ConnectionInterface
{
    return new class ($rows) implements ConnectionInterface
    {
        public function __construct(private readonly array $rows) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            return $this->rows;
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
}

function makeWithQbFactory(array $relatedRows = []): QueryBuilderFactoryInterface
{
    $stub = makeWithStubBuilder($relatedRows);

    return new class ($stub) implements QueryBuilderFactoryInterface
    {
        public function __construct(private readonly QueryBuilderInterface $builder) {}

        public function create(): QueryBuilderInterface
        {
            return $this->builder;
        }
    };
}

function makeWithLoader(array $relatedRows = []): RelationshipLoader
{
    $factory = makeWithQbFactory($relatedRows);

    return new RelationshipLoader(
        new EntityMetadataFactory(),
        new EntityHydrator(),
        $factory,
    );
}

function makeWithRepository(
    array $connectionRows = [],
    ?RelationshipLoader $loader = null,
    ?QueryBuilderFactoryInterface $qbFactory = null,
): WithUserRepository {
    return new WithUserRepository(
        connection: makeWithConnection($connectionRows),
        metadataFactory: new EntityMetadataFactory(),
        hydrator: new EntityHydrator(),
        queryBuilderFactory: $qbFactory,
        eventDispatcher: null,
        relationshipLoader: $loader,
    );
}

// ── Tests ──────────────────────────────────────────────────────────────────────

describe('with() Method', function (): void {
    it('returns a new instance from with to avoid shared state', function (): void {
        $repo = makeWithRepository(loader: makeWithLoader());
        $cloned = $repo->with('profile');

        expect($cloned)->not->toBe($repo);
    });

    it('accepts variadic string relationship names', function (): void {
        $repo = makeWithRepository(loader: makeWithLoader());
        $cloned = $repo->with('profile', 'posts', 'country');

        expect($cloned)->not->toBe($repo);
    });

    it('chains with find to load BelongsTo relationship', function (): void {
        $userRow = ['id' => 1, 'name' => 'Alice', 'country_id' => 5];
        $factory = makeWithQbFactory([['id' => 5, 'name' => 'Canada']]);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection([$userRow]),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $entity = $repo->with('country')->find(1);

        expect($entity)->toBeInstanceOf(WithUser::class)
            ->and($entity->country)->toBeInstanceOf(WithCountry::class)
            ->and($entity->country->name)->toBe('Canada');
    });

    it('chains with find to load HasMany relationship', function (): void {
        $userRow = ['id' => 1, 'name' => 'Alice', 'country_id' => null];
        $postRows = [
            ['id' => 10, 'user_id' => 1, 'title' => 'First Post'],
            ['id' => 11, 'user_id' => 1, 'title' => 'Second Post'],
        ];
        $factory = makeWithQbFactory($postRows);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection([$userRow]),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $entity = $repo->with('posts')->find(1);

        expect($entity)->toBeInstanceOf(WithUser::class)
            ->and($entity->posts)->toHaveCount(2);
    });

    it('chains with find to load HasOne relationship', function (): void {
        $userRow = ['id' => 1, 'name' => 'Alice', 'country_id' => null];
        $profileRows = [['id' => 20, 'user_id' => 1]];
        $factory = makeWithQbFactory($profileRows);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection([$userRow]),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $entity = $repo->with('profile')->find(1);

        expect($entity)->toBeInstanceOf(WithUser::class)
            ->and($entity->profile)->toBeInstanceOf(WithProfile::class);
    });

    it('chains with findAll to load relationships on all entities', function (): void {
        $userRows = [
            ['id' => 1, 'name' => 'Alice', 'country_id' => null],
            ['id' => 2, 'name' => 'Bob', 'country_id' => null],
        ];
        $postRows = [
            ['id' => 10, 'user_id' => 1, 'title' => 'Post A'],
            ['id' => 11, 'user_id' => 2, 'title' => 'Post B'],
        ];
        $factory = makeWithQbFactory($postRows);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection($userRows),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $collection = $repo->with('posts')->findAll();
        $entities = $collection->toArray();

        expect($collection)->toBeInstanceOf(EntityCollection::class)
            ->and($entities[0]->posts)->toHaveCount(1)
            ->and($entities[1]->posts)->toHaveCount(1);
    });

    it('chains with findBy to load relationships on matched entities', function (): void {
        $userRows = [
            ['id' => 1, 'name' => 'Alice', 'country_id' => null],
        ];
        $profileRows = [['id' => 20, 'user_id' => 1]];
        $factory = makeWithQbFactory($profileRows);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection($userRows),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $collection = $repo->with('profile')->findBy(['name' => 'Alice']);
        $entities = $collection->toArray();

        expect($entities[0]->profile)->toBeInstanceOf(WithProfile::class);
    });

    it('chains with findOneBy to load relationships on single entity', function (): void {
        $userRows = [
            ['id' => 1, 'name' => 'Alice', 'country_id' => null],
        ];
        $profileRows = [['id' => 20, 'user_id' => 1]];
        $factory = makeWithQbFactory($profileRows);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new WithUserRepository(
            connection: makeWithConnection($userRows),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $entity = $repo->with('profile')->findOneBy(['name' => 'Alice']);

        expect($entity)->toBeInstanceOf(WithUser::class)
            ->and($entity->profile)->toBeInstanceOf(WithProfile::class);
    });
});

describe('Eager Loading Integration', function (): void {
    it('passes loaded entities to RelationshipLoader', function (): void {
        $userRows = [['id' => 1, 'name' => 'Alice', 'country_id' => null]];
        // Track whether the query builder was used (indicating load was called)
        $queryCalled = false;

        $trackingBuilder = new class ($queryCalled) implements QueryBuilderInterface
        {
            public bool $getCalled = false;

            public function __construct(bool &$queryCalled)
            {
                // store reference not needed; use getCalled instead
            }

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
                return $this;
            }

            public function whereIn(string $column, array $values): static
            {
                $this->getCalled = true;
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
                return [];
            }

            public function first(): ?array
            {
                return null;
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
                return 0;
            }

            public function raw(string $sql, array $bindings = []): array
            {
                return [];
            }
        };

        $trackingFactory = new class ($trackingBuilder) implements QueryBuilderFactoryInterface
        {
            public function __construct(private readonly QueryBuilderInterface $builder) {}

            public function create(): QueryBuilderInterface
            {
                return $this->builder;
            }
        };

        $loader = new RelationshipLoader(
            new EntityMetadataFactory(),
            new EntityHydrator(),
            $trackingFactory,
        );

        $repo = new WithUserRepository(
            connection: makeWithConnection($userRows),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: null,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $repo->with('profile')->findAll();

        expect($trackingBuilder->getCalled)->toBeTrue();
    });

    it('returns EntityCollection from findAll with relationships loaded', function (): void {
        $userRows = [['id' => 1, 'name' => 'Alice', 'country_id' => null]];
        $loader = makeWithLoader();
        $repo = makeWithRepository($userRows, $loader);

        $result = $repo->with('profile')->findAll();

        expect($result)->toBeInstanceOf(EntityCollection::class);
    });

    it('returns null from find when entity not found without loading relationships', function (): void {
        $queryCalled = false;

        $trackingBuilder = new class ($queryCalled) implements QueryBuilderInterface
        {
            public bool $whereInCalled = false;

            public function __construct(bool &$queryCalled) {}

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
                return $this;
            }

            public function whereIn(string $column, array $values): static
            {
                $this->whereInCalled = true;
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
                return [];
            }

            public function first(): ?array
            {
                return null;
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
                return 0;
            }

            public function raw(string $sql, array $bindings = []): array
            {
                return [];
            }
        };

        $trackingFactory = new class ($trackingBuilder) implements QueryBuilderFactoryInterface
        {
            public function __construct(private readonly QueryBuilderInterface $builder) {}

            public function create(): QueryBuilderInterface
            {
                return $this->builder;
            }
        };

        $loader = new RelationshipLoader(
            new EntityMetadataFactory(),
            new EntityHydrator(),
            $trackingFactory,
        );

        $repo = new WithUserRepository(
            connection: makeWithConnection([]),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: null,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $entity = $repo->with('profile')->find(999);

        expect($entity)->toBeNull()
            ->and($trackingBuilder->whereInCalled)->toBeFalse();
    });

    it('loads multiple relationships when multiple names specified', function (): void {
        $userRows = [['id' => 1, 'name' => 'Alice', 'country_id' => null]];

        $callCount = 0;

        $countingBuilder = new class ($callCount) implements QueryBuilderInterface
        {
            public int $whereInCount = 0;

            public function __construct(int &$callCount) {}

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
                return $this;
            }

            public function whereIn(string $column, array $values): static
            {
                $this->whereInCount++;
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
                return [];
            }

            public function first(): ?array
            {
                return null;
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
                return 0;
            }

            public function raw(string $sql, array $bindings = []): array
            {
                return [];
            }
        };

        $countingFactory = new class ($countingBuilder) implements QueryBuilderFactoryInterface
        {
            public function __construct(private readonly QueryBuilderInterface $builder) {}

            public function create(): QueryBuilderInterface
            {
                return $this->builder;
            }
        };

        $loader = new RelationshipLoader(
            new EntityMetadataFactory(),
            new EntityHydrator(),
            $countingFactory,
        );

        $repo = new WithUserRepository(
            connection: makeWithConnection($userRows),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: null,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        $repo->with('profile', 'posts')->findAll();

        // profile (HasOne) and posts (HasMany) each call whereIn once
        expect($countingBuilder->whereInCount)->toBe(2);
    });
});

describe('Eager Validation', function (): void {
    it('throws RepositoryException when with is called with unknown relationship name', function (): void {
        $repo = makeWithRepository(loader: makeWithLoader());

        expect(fn () => $repo->with('nonExistentRelationship'))->toThrow(RepositoryException::class);
    });

    it('validates all relationship names against entity metadata before cloning', function (): void {
        $repo = makeWithRepository(loader: makeWithLoader());

        expect(fn () => $repo->with('profile', 'badRelation'))->toThrow(RepositoryException::class);
    });
});

describe('Without RelationshipLoader', function (): void {
    it('works without RelationshipLoader when no with is called', function (): void {
        $repo = makeWithRepository();
        $result = $repo->findAll();

        expect($result)->toBeInstanceOf(EntityCollection::class);
    });

    it('throws RepositoryException when with is called but RelationshipLoader is not configured', function (): void {
        $repo = makeWithRepository();

        expect(fn () => $repo->with('profile'))->toThrow(RepositoryException::class);
    });
});
