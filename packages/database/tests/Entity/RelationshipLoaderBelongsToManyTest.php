<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

// ── Test Entity Fixtures ───────────────────────────────────────────────────────

#[Table('posts')]
class BtmPost extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $title = '';

    /** @var BtmTag[] */
    public array $tags = [];
}

#[Table('tags')]
class BtmTag extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

#[Table('post_tags')]
class BtmPostTag extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $postId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public int $tagId = 0;
}

#[Table('post_tags')]
class BtmPostTagExtra extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $postId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public int $tagId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $role = '';
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeBtmPostMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: BtmPost::class,
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
            'title' => new PropertyMetadata(
                name: 'title',
                columnName: 'title',
                type: 'string',
            ),
        ],
    );
}

function makeBtmTagMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: BtmTag::class,
        tableName: 'tags',
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

function makeBtmPostTagMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: BtmPostTag::class,
        tableName: 'post_tags',
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
            'postId' => new PropertyMetadata(
                name: 'postId',
                columnName: 'post_id',
                type: 'int',
            ),
            'tagId' => new PropertyMetadata(
                name: 'tagId',
                columnName: 'tag_id',
                type: 'int',
            ),
        ],
    );
}

function makeBtmPostTagExtraMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: BtmPostTagExtra::class,
        tableName: 'post_tags',
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
            'postId' => new PropertyMetadata(
                name: 'postId',
                columnName: 'post_id',
                type: 'int',
            ),
            'tagId' => new PropertyMetadata(
                name: 'tagId',
                columnName: 'tag_id',
                type: 'int',
            ),
            'role' => new PropertyMetadata(
                name: 'role',
                columnName: 'role',
                type: 'string',
            ),
        ],
    );
}

function makeBtmFakeQueryBuilder(array $rows): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
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

/**
 * Creates a factory that returns different rows per `create()` call (in order).
 */
function makeBtmMultiResponseFactory(array $responsesQueue): QueryBuilderFactoryInterface
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

            return makeBtmFakeQueryBuilder($rows);
        }
    };
}

/**
 * Creates a factory that returns rows per call AND tracks whereIn queries with table context.
 *
 * @param array<int, array<int, array<string, mixed>>> $responsesQueue
 * @param array<array{table: string, column: string, values: array<mixed>}> $queries
 */
function makeBtmDataAndTrackingFactory(array $responsesQueue, array &$queries): QueryBuilderFactoryInterface
{
    return new class ($responsesQueue, $queries) implements QueryBuilderFactoryInterface
    {
        private int $callCount = 0;

        public function __construct(
            private array $responsesQueue,
            private array &$queries,
        ) {}

        public function create(): QueryBuilderInterface
        {
            $rows = $this->responsesQueue[$this->callCount] ?? [];
            $this->callCount++;
            $queries = &$this->queries;

            return new class ($rows, $queries) implements QueryBuilderInterface
            {
                private string $currentTable = '';

                public function __construct(
                    private array $rows,
                    private array &$queries,
                ) {}

                public function table(string $table): static
                {
                    $this->currentTable = $table;

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
                    $this->queries[] = ['table' => $this->currentTable, 'column' => $column, 'values' => $values];

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

function makeBtmMetadataFactory(array $metadataMap): EntityMetadataFactory
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

function makeBtmLoader(QueryBuilderFactoryInterface $factory, EntityMetadataFactory $metadataFactory): RelationshipLoader
{
    return new RelationshipLoader(
        entityMetadataFactory: $metadataFactory,
        entityHydrator: new EntityHydrator(),
        queryBuilderFactory: $factory,
    );
}

// ── BelongsToMany Loading ──────────────────────────────────────────────────────

it('loads BelongsToMany relationship for a single entity', function (): void {
    $post = new BtmPost();
    $post->id = 1;
    $post->title = 'Hello';

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $qbFactory = makeBtmMultiResponseFactory([
        [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
        [['id' => 10, 'name' => 'PHP']],
    ]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($post->tags)->toHaveCount(1)
        ->and($post->tags[0])->toBeInstanceOf(BtmTag::class)
        ->and($post->tags[0]->id)->toBe(10)
        ->and($post->tags[0]->name)->toBe('PHP');
});

it('batch loads BelongsToMany relationship for multiple entities', function (): void {
    $post1 = new BtmPost();
    $post1->id = 1;

    $post2 = new BtmPost();
    $post2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $qbFactory = makeBtmMultiResponseFactory([
        [
            ['id' => 1, 'post_id' => 1, 'tag_id' => 10],
            ['id' => 2, 'post_id' => 2, 'tag_id' => 20],
        ],
        [
            ['id' => 10, 'name' => 'PHP'],
            ['id' => 20, 'name' => 'Laravel'],
        ],
    ]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post1, $post2], $relationship, $postMeta);

    expect($post1->tags)->toHaveCount(1)
        ->and($post1->tags[0]->name)->toBe('PHP')
        ->and($post2->tags)->toHaveCount(1)
        ->and($post2->tags[0]->name)->toBe('Laravel');
});

it('sets BelongsToMany property to empty array when no related entities found', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $qbFactory = makeBtmMultiResponseFactory([[], []]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($post->tags)->toBe([]);
});

it('resolves through pivot table using two queries', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $queriesTracked = [];
    $qbFactory = makeBtmDataAndTrackingFactory(
        [
            [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
            [['id' => 10, 'name' => 'PHP']],
        ],
        $queriesTracked,
    );

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($queriesTracked)->toHaveCount(2);
});

it('correctly maps related entities back to parent entities', function (): void {
    $post1 = new BtmPost();
    $post1->id = 1;

    $post2 = new BtmPost();
    $post2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    // post1 -> tag 10 + tag 20; post2 -> tag 20 only
    $qbFactory = makeBtmMultiResponseFactory([
        [
            ['id' => 1, 'post_id' => 1, 'tag_id' => 10],
            ['id' => 2, 'post_id' => 1, 'tag_id' => 20],
            ['id' => 3, 'post_id' => 2, 'tag_id' => 20],
        ],
        [
            ['id' => 10, 'name' => 'PHP'],
            ['id' => 20, 'name' => 'Laravel'],
        ],
    ]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post1, $post2], $relationship, $postMeta);

    expect($post1->tags)->toHaveCount(2)
        ->and($post1->tags[0]->name)->toBe('PHP')
        ->and($post1->tags[1]->name)->toBe('Laravel')
        ->and($post2->tags)->toHaveCount(1)
        ->and($post2->tags[0]->name)->toBe('Laravel');
});

// ── Pivot Entity Handling ──────────────────────────────────────────────────────

it('reads pivot table name from pivot entity metadata', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $queriesTracked = [];
    $qbFactory = makeBtmDataAndTrackingFactory(
        [
            [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
            [['id' => 10, 'name' => 'PHP']],
        ],
        $queriesTracked,
    );

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($queriesTracked[0]['table'])->toBe('post_tags');
});

it('uses foreign key column to query pivot table', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $queriesTracked = [];
    $qbFactory = makeBtmDataAndTrackingFactory(
        [
            [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
            [['id' => 10, 'name' => 'PHP']],
        ],
        $queriesTracked,
    );

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($queriesTracked[0]['column'])->toBe('post_id')
        ->and($queriesTracked[0]['values'])->toBe([1]);
});

it('uses related key column to query related table', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $queriesTracked = [];
    $qbFactory = makeBtmDataAndTrackingFactory(
        [
            [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
            [['id' => 10, 'name' => 'PHP']],
        ],
        $queriesTracked,
    );

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($queriesTracked[1]['table'])->toBe('tags')
        ->and($queriesTracked[1]['column'])->toBe('id')
        ->and($queriesTracked[1]['values'])->toBe([10]);
});

it('handles pivot entities with extra columns', function (): void {
    $post = new BtmPost();
    $post->id = 1;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTagExtra::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagExtraMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTagExtra::class => $pivotMeta,
    ]);

    $qbFactory = makeBtmMultiResponseFactory([
        [['id' => 1, 'post_id' => 1, 'tag_id' => 10, 'role' => 'primary']],
        [['id' => 10, 'name' => 'PHP']],
    ]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post], $relationship, $postMeta);

    expect($post->tags)->toHaveCount(1)
        ->and($post->tags[0]->name)->toBe('PHP');
});

// ── Batch Optimization ─────────────────────────────────────────────────────────

it('deduplicates related IDs across parents in batch query', function (): void {
    $post1 = new BtmPost();
    $post1->id = 1;

    $post2 = new BtmPost();
    $post2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    $queriesTracked = [];

    // Both posts share tag 10; post1 also has tag 20
    $qbFactory = makeBtmDataAndTrackingFactory(
        [
            [
                ['id' => 1, 'post_id' => 1, 'tag_id' => 10],
                ['id' => 2, 'post_id' => 2, 'tag_id' => 10],
                ['id' => 3, 'post_id' => 1, 'tag_id' => 20],
            ],
            [
                ['id' => 10, 'name' => 'PHP'],
                ['id' => 20, 'name' => 'Laravel'],
            ],
        ],
        $queriesTracked,
    );

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post1, $post2], $relationship, $postMeta);

    // Second query (related table) should have deduplicated tag IDs: [10, 20] not [10, 10, 20]
    expect($queriesTracked[1]['values'])->toBe([10, 20]);
});

it('handles parents with no pivot rows', function (): void {
    $post1 = new BtmPost();
    $post1->id = 1;

    $post2 = new BtmPost();
    $post2->id = 2;

    $relationship = new RelationshipMetadata(
        propertyName: 'tags',
        type: RelationshipType::BelongsToMany,
        relatedClass: BtmTag::class,
        foreignKey: 'postId',
        relatedKey: 'tagId',
        pivotClass: BtmPostTag::class,
    );

    $postMeta = makeBtmPostMetadata();
    $tagMeta = makeBtmTagMetadata();
    $pivotMeta = makeBtmPostTagMetadata();

    $metadataFactory = makeBtmMetadataFactory([
        BtmPost::class => $postMeta,
        BtmTag::class => $tagMeta,
        BtmPostTag::class => $pivotMeta,
    ]);

    // Only post1 has a pivot row; post2 has none
    $qbFactory = makeBtmMultiResponseFactory([
        [['id' => 1, 'post_id' => 1, 'tag_id' => 10]],
        [['id' => 10, 'name' => 'PHP']],
    ]);

    $loader = makeBtmLoader($qbFactory, $metadataFactory);
    $loader->load([$post1, $post2], $relationship, $postMeta);

    expect($post1->tags)->toHaveCount(1)
        ->and($post2->tags)->toBe([]);
});
