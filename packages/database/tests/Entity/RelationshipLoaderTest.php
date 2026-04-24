<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use ReflectionClass;

// ── Test Entity Fixtures ───────────────────────────────────────────────────────

#[Table('users')]
class LoaderUser extends Entity
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

    public ?LoaderProfile $profile = null;

    /** @var LoaderPost[] */
    public array $posts = [];

    public ?LoaderCountry $country = null;
}

#[Table('profiles')]
class LoaderProfile extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $userId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $bio = '';
}

#[Table('posts')]
class LoaderPost extends Entity
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

#[Table('countries')]
class LoaderCountry extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

#[Table('authors')]
class LoaderAuthorWithCollection extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';

    /** @var EntityCollection<LoaderPost> */
    public EntityCollection $posts;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeUserMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: LoaderUser::class,
        tableName: 'users',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
            'name' => new PropertyMetadata(
                name: 'name',
                columnName: 'name',
                type: 'string',
            ),
            'countryId' => new PropertyMetadata(
                name: 'countryId',
                columnName: 'country_id',
                type: 'int',
                nullable: true,
            ),
        ],
    );
}

function makeProfileMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: LoaderProfile::class,
        tableName: 'profiles',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
            'userId' => new PropertyMetadata(
                name: 'userId',
                columnName: 'user_id',
                type: 'int',
            ),
            'bio' => new PropertyMetadata(
                name: 'bio',
                columnName: 'bio',
                type: 'string',
            ),
        ],
    );
}

function makePostMetadataForLoader(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: LoaderPost::class,
        tableName: 'posts',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
            'userId' => new PropertyMetadata(
                name: 'userId',
                columnName: 'user_id',
                type: 'int',
            ),
            'title' => new PropertyMetadata(
                name: 'title',
                columnName: 'title',
                type: 'string',
            ),
        ],
    );
}

function makeAuthorWithCollectionMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: LoaderAuthorWithCollection::class,
        tableName: 'authors',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
            'name' => new PropertyMetadata(
                name: 'name',
                columnName: 'name',
                type: 'string',
            ),
        ],
    );
}

function makeCountryMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: LoaderCountry::class,
        tableName: 'countries',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
            'name' => new PropertyMetadata(
                name: 'name',
                columnName: 'name',
                type: 'string',
            ),
        ],
    );
}

function makeFakeQueryBuilder(array $rows): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
        private array $capturedColumns = [];

        private array $capturedValues = [];

        public function __construct(
            private array $rows,
        ) {}

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function where(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function whereIn(
            string $column,
            array $values,
        ): static {
            $this->capturedColumns[] = $column;
            $this->capturedValues[] = $values;

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

        public function whereJsonContains(string $path, mixed $value): static
        {
            return $this;
        }

        public function whereJsonExists(string $path): static
        {
            return $this;
        }

        public function whereJsonMissing(string $path): static
        {
            return $this;
        }

        public function orWhere(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function join(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function leftJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function rightJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function orderBy(
            string $column,
            string $direction = 'ASC',
        ): static {
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

        public function distinct(): static
        {
            return $this;
        }

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function getColumnCount(): int
        {
            return 1;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
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

        public function count(?string $column = null): int
        {
            return count($this->rows);
        }

        public function raw(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function min(string $column): int|float|null
        {
            return null;
        }

        public function max(string $column): int|float|null
        {
            return null;
        }

        public function sum(string $column): int|float|null
        {
            return null;
        }

        public function avg(string $column): int|float|null
        {
            return null;
        }
    };
}

function makeFakeQueryBuilderFactory(array $rows): QueryBuilderFactoryInterface
{
    return new class ($rows) implements QueryBuilderFactoryInterface
    {
        public function __construct(
            private array $rows,
        ) {}

        public function create(): QueryBuilderInterface
        {
            return makeFakeQueryBuilder($this->rows);
        }
    };
}

function makeTrackingQueryBuilderFactory(array &$queries): QueryBuilderFactoryInterface
{
    return new class ($queries) implements QueryBuilderFactoryInterface
    {
        public function __construct(
            private array &$queries,
        ) {}

        public function create(): QueryBuilderInterface
        {
            $queries = &$this->queries;

            return new class ($queries) implements QueryBuilderInterface
            {
                private array $rows = [];

                public function __construct(
                    private array &$queries,
                ) {}

                public function table(string $table): static
                {
                    return $this;
                }

                public function select(string ...$columns): static
                {
                    return $this;
                }

                public function where(
                    string $column,
                    string $operator,
                    mixed $value,
                ): static {
                    return $this;
                }

                public function whereIn(
                    string $column,
                    array $values,
                ): static {
                    $this->queries[] = ['column' => $column, 'values' => $values];

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

                public function whereJsonContains(string $path, mixed $value): static
                {
                    return $this;
                }

                public function whereJsonExists(string $path): static
                {
                    return $this;
                }

                public function whereJsonMissing(string $path): static
                {
                    return $this;
                }

                public function orWhere(
                    string $column,
                    string $operator,
                    mixed $value,
                ): static {
                    return $this;
                }

                public function join(
                    string $table,
                    string $first,
                    string $operator,
                    string $second,
                ): static {
                    return $this;
                }

                public function leftJoin(
                    string $table,
                    string $first,
                    string $operator,
                    string $second,
                ): static {
                    return $this;
                }

                public function rightJoin(
                    string $table,
                    string $first,
                    string $operator,
                    string $second,
                ): static {
                    return $this;
                }

                public function orderBy(
                    string $column,
                    string $direction = 'ASC',
                ): static {
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

                public function distinct(): static
                {
                    return $this;
                }

                public function union(QueryBuilderInterface $other): static
                {
                    return $this;
                }

                public function unionAll(QueryBuilderInterface $other): static
                {
                    return $this;
                }

                public function getColumnCount(): int
                {
                    return 1;
                }

                public function compileSubquery(array &$bindings): string
                {
                    return '';
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

                public function count(?string $column = null): int
                {
                    return count($this->rows);
                }

                public function raw(
                    string $sql,
                    array $bindings = [],
                ): array {
                    return [];
                }

                public function groupBy(string ...$columns): static
                {
                    return $this;
                }

                public function having(string $expression, array $bindings = []): static
                {
                    return $this;
                }

                public function min(string $column): int|float|null
                {
                    return null;
                }

                public function max(string $column): int|float|null
                {
                    return null;
                }

                public function sum(string $column): int|float|null
                {
                    return null;
                }

                public function avg(string $column): int|float|null
                {
                    return null;
                }
            };
        }
    };
}

function makeMultiResponseQueryBuilderFactory(array $responsesQueue): QueryBuilderFactoryInterface
{
    return new class ($responsesQueue) implements QueryBuilderFactoryInterface
    {
        private int $callCount = 0;

        public function __construct(
            private array $responsesQueue,
        ) {}

        public function create(): QueryBuilderInterface
        {
            $rows = $this->responsesQueue[$this->callCount] ?? [];
            $this->callCount++;

            return makeFakeQueryBuilder($rows);
        }
    };
}

function makeLoader(QueryBuilderFactoryInterface $factory, ?EntityMetadataFactory $metadataFactory = null): RelationshipLoader
{
    $metadataFactory ??= new EntityMetadataFactory();

    return new RelationshipLoader(
        entityMetadataFactory: $metadataFactory,
        entityHydrator: new EntityHydrator(),
        queryBuilderFactory: $factory,
    );
}

function makeParentMetadataFactory(array $metadataMap): EntityMetadataFactory
{
    return new class ($metadataMap) extends EntityMetadataFactory
    {
        public function __construct(
            private array $metadataMap,
        ) {}

        public function parse(string $entityClass): EntityMetadata
        {
            return $this->metadataMap[$entityClass];
        }
    };
}

// ── BelongsTo Loading ──────────────────────────────────────────────────────────

it('loads BelongsTo relationship for a single entity', function (): void {
    $user = new LoaderUser();
    $user->id = 1;
    $user->name = 'Alice';
    $user->countryId = 10;

    $relationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'name' => 'France'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->country)->toBeInstanceOf(LoaderCountry::class)
        ->and($user->country->id)->toBe(10)
        ->and($user->country->name)->toBe('France');
});

it('batch loads BelongsTo relationship for multiple entities', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;
    $user1->countryId = 10;

    $user2 = new LoaderUser();
    $user2->id = 2;
    $user2->countryId = 20;

    $relationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'name' => 'France'],
        ['id' => 20, 'name' => 'Germany'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2], $relationship, $userMeta);

    expect($user1->country)->toBeInstanceOf(LoaderCountry::class)
        ->and($user1->country->id)->toBe(10)
        ->and($user2->country)->toBeInstanceOf(LoaderCountry::class)
        ->and($user2->country->id)->toBe(20);
});

it('sets BelongsTo property to null when related entity not found', function (): void {
    $user = new LoaderUser();
    $user->id = 1;
    $user->countryId = 999;

    $relationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->country)->toBeNull();
});

it('deduplicates foreign key values in batch query', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;
    $user1->countryId = 10;

    $user2 = new LoaderUser();
    $user2->id = 2;
    $user2->countryId = 10;

    $relationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $queries = [];
    $qbFactory = makeTrackingQueryBuilderFactory($queries);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2], $relationship, $userMeta);

    expect($queries)->toHaveCount(1)
        ->and($queries[0]['values'])->toBe([10]);
});

// ── HasOne Loading ─────────────────────────────────────────────────────────────

it('loads HasOne relationship for a single entity', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 5, 'user_id' => 1, 'bio' => 'A bio'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->profile)->toBeInstanceOf(LoaderProfile::class)
        ->and($user->profile->id)->toBe(5)
        ->and($user->profile->userId)->toBe(1);
});

it('batch loads HasOne relationship for multiple entities', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;

    $user2 = new LoaderUser();
    $user2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 5, 'user_id' => 1, 'bio' => 'Bio 1'],
        ['id' => 6, 'user_id' => 2, 'bio' => 'Bio 2'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2], $relationship, $userMeta);

    expect($user1->profile)->toBeInstanceOf(LoaderProfile::class)
        ->and($user1->profile->userId)->toBe(1)
        ->and($user2->profile)->toBeInstanceOf(LoaderProfile::class)
        ->and($user2->profile->userId)->toBe(2);
});

it('sets HasOne property to null when related entity not found', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->profile)->toBeNull();
});

// ── HasMany Loading ────────────────────────────────────────────────────────────

it('loads HasMany relationship for a single entity', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'user_id' => 1, 'title' => 'Post A'],
        ['id' => 11, 'user_id' => 1, 'title' => 'Post B'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->posts)->toHaveCount(2)
        ->and($user->posts[0])->toBeInstanceOf(LoaderPost::class)
        ->and($user->posts[0]->title)->toBe('Post A')
        ->and($user->posts[1]->title)->toBe('Post B');
});

it('batch loads HasMany relationship for multiple entities', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;

    $user2 = new LoaderUser();
    $user2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'user_id' => 1, 'title' => 'Post A'],
        ['id' => 11, 'user_id' => 2, 'title' => 'Post B'],
        ['id' => 12, 'user_id' => 1, 'title' => 'Post C'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2], $relationship, $userMeta);

    expect($user1->posts)->toHaveCount(2)
        ->and($user2->posts)->toHaveCount(1)
        ->and($user2->posts[0]->title)->toBe('Post B');
});

it('sets HasMany property to empty array when no related entities found', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->posts)->toBe([]);
});

it('groups HasMany results by foreign key value', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;

    $user2 = new LoaderUser();
    $user2->id = 2;

    $user3 = new LoaderUser();
    $user3->id = 3;

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'user_id' => 1, 'title' => 'A1'],
        ['id' => 11, 'user_id' => 1, 'title' => 'A2'],
        ['id' => 12, 'user_id' => 1, 'title' => 'A3'],
        ['id' => 20, 'user_id' => 2, 'title' => 'B1'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2, $user3], $relationship, $userMeta);

    expect($user1->posts)->toHaveCount(3)
        ->and($user2->posts)->toHaveCount(1)
        ->and($user3->posts)->toBe([]);
});

// ── Batch Query Optimization ───────────────────────────────────────────────────

it('executes single query for same relationship across multiple entities', function (): void {
    $user1 = new LoaderUser();
    $user1->id = 1;

    $user2 = new LoaderUser();
    $user2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
    ]);

    $queries = [];
    $qbFactory = makeTrackingQueryBuilderFactory($queries);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user1, $user2], $relationship, $userMeta);

    expect($queries)->toHaveCount(1);
});

it('skips loading when all foreign key values are null', function (): void {
    $user = new LoaderUser();
    $user->id = 1;
    $user->countryId = null;

    $relationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $queries = [];
    $qbFactory = makeTrackingQueryBuilderFactory($queries);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($queries)->toHaveCount(0)
        ->and($user->country)->toBeNull();
});

it('loads multiple different relationships in separate queries', function (): void {
    $user = new LoaderUser();
    $user->id = 1;
    $user->countryId = 10;

    $profileRelationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $countryRelationship = new RelationshipMetadata(
        propertyName: 'country',
        type: RelationshipType::BelongsTo,
        relatedClass: LoaderCountry::class,
        foreignKey: 'countryId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();
    $countryMeta = makeCountryMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
        LoaderCountry::class => $countryMeta,
    ]);

    $queries = [];
    $qbFactory = makeTrackingQueryBuilderFactory($queries);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $profileRelationship, $userMeta);
    $loader->load([$user], $countryRelationship, $userMeta);

    expect($queries)->toHaveCount(2);
});

// ── Entity Hydration ───────────────────────────────────────────────────────────

it('hydrates related entities with correct types', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'profile',
        type: RelationshipType::HasOne,
        relatedClass: LoaderProfile::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $profileMeta = makeProfileMetadata();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderProfile::class => $profileMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => '5', 'user_id' => '1', 'bio' => 'My bio'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    expect($user->profile)->toBeInstanceOf(LoaderProfile::class)
        ->and($user->profile->id)->toBe(5)
        ->and($user->profile->id)->toBeInt()
        ->and($user->profile->userId)->toBe(1)
        ->and($user->profile->userId)->toBeInt()
        ->and($user->profile->bio)->toBe('My bio');
});

it('sets related entity properties via reflection', function (): void {
    $user = new LoaderUser();
    $user->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $userMeta = makeUserMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderUser::class => $userMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'user_id' => 1, 'title' => 'Test Post'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$user], $relationship, $userMeta);

    $ref = new ReflectionClass($user);
    $prop = $ref->getProperty('posts');
    $value = $prop->getValue($user);

    expect($value)->toHaveCount(1)
        ->and($value[0])->toBeInstanceOf(LoaderPost::class)
        ->and($value[0]->title)->toBe('Test Post');
});

// ── EntityCollection-typed Properties ──────────────────────────────────────────

it('wraps HasMany results in EntityCollection when property is typed as EntityCollection', function (): void {
    $author = new LoaderAuthorWithCollection();
    $author->id = 1;
    $author->name = 'Alice';

    $relationship = new RelationshipMetadata(
        propertyName: 'posts',
        type: RelationshipType::HasMany,
        relatedClass: LoaderPost::class,
        foreignKey: 'userId',
    );

    $authorMeta = makeAuthorWithCollectionMetadata();
    $postMeta = makePostMetadataForLoader();

    $factory = makeParentMetadataFactory([
        LoaderAuthorWithCollection::class => $authorMeta,
        LoaderPost::class => $postMeta,
    ]);

    $qbFactory = makeFakeQueryBuilderFactory([
        ['id' => 10, 'user_id' => 1, 'title' => 'Post A'],
        ['id' => 11, 'user_id' => 1, 'title' => 'Post B'],
    ]);

    $loader = makeLoader($qbFactory, $factory);
    $loader->load([$author], $relationship, $authorMeta);

    expect($author->posts)->toBeInstanceOf(EntityCollection::class)
        ->and($author->posts)->toHaveCount(2)
        ->and($author->posts->toArray()[0])->toBeInstanceOf(LoaderPost::class)
        ->and($author->posts->toArray()[0]->title)->toBe('Post A')
        ->and($author->posts->toArray()[1]->title)->toBe('Post B');
});

it(
    'assigns empty EntityCollection when no related entities found for EntityCollection-typed property',
    function (): void {
        $author = new LoaderAuthorWithCollection();
        $author->id = 1;
        $author->name = 'Alice';

        $relationship = new RelationshipMetadata(
            propertyName: 'posts',
            type: RelationshipType::HasMany,
            relatedClass: LoaderPost::class,
            foreignKey: 'userId',
        );

        $authorMeta = makeAuthorWithCollectionMetadata();
        $postMeta = makePostMetadataForLoader();

        $factory = makeParentMetadataFactory([
            LoaderAuthorWithCollection::class => $authorMeta,
            LoaderPost::class => $postMeta,
        ]);

        $qbFactory = makeFakeQueryBuilderFactory([]);

        $loader = makeLoader($qbFactory, $factory);
        $loader->load([$author], $relationship, $authorMeta);

        expect($author->posts)->toBeInstanceOf(EntityCollection::class)
            ->and($author->posts)->toHaveCount(0)
            ->and($author->posts->isEmpty())->toBeTrue();
    },
);
