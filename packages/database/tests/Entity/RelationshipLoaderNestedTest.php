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

#[Table('nested_authors')]
class NestedAuthor extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

#[Table('nested_comments')]
class NestedComment extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $postId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public int $authorId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $body = '';

    public ?NestedAuthor $author = null;
}

#[Table('nested_categories')]
class NestedCategory extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $title = '';
}

#[Table('nested_posts')]
class NestedPost extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $categoryId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $title = '';

    /** @var NestedComment[] */
    public array $comments = [];

    public ?NestedCategory $category = null;
}

#[Table('nested_tags')]
class NestedTag extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $commentId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

// For NestedComment with tags
#[Table('nested_comments_with_tags')]
class NestedCommentWithTags extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $postId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public int $authorId = 0;

    /** @noinspection PhpUnused */
    #[Column]
    public string $body = '';

    public ?NestedAuthor $author = null;

    /** @var NestedTag[] */
    public array $tags = [];
}

// ── Metadata Helpers ───────────────────────────────────────────────────────────

function makeNestedAuthorMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedAuthor::class,
        tableName: 'nested_authors',
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

function makeNestedCommentMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedComment::class,
        tableName: 'nested_comments',
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
            'authorId' => new PropertyMetadata(
                name: 'authorId',
                columnName: 'author_id',
                type: 'int',
            ),
            'body' => new PropertyMetadata(
                name: 'body',
                columnName: 'body',
                type: 'string',
            ),
        ],
        relationships: [
            'author' => new RelationshipMetadata(
                propertyName: 'author',
                type: RelationshipType::BelongsTo,
                relatedClass: NestedAuthor::class,
                foreignKey: 'authorId',
            ),
        ],
    );
}

function makeNestedCategoryMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedCategory::class,
        tableName: 'nested_categories',
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

function makeNestedPostMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedPost::class,
        tableName: 'nested_posts',
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
            'categoryId' => new PropertyMetadata(
                name: 'categoryId',
                columnName: 'category_id',
                type: 'int',
            ),
            'title' => new PropertyMetadata(
                name: 'title',
                columnName: 'title',
                type: 'string',
            ),
        ],
        relationships: [
            'comments' => new RelationshipMetadata(
                propertyName: 'comments',
                type: RelationshipType::HasMany,
                relatedClass: NestedComment::class,
                foreignKey: 'postId',
            ),
            'category' => new RelationshipMetadata(
                propertyName: 'category',
                type: RelationshipType::BelongsTo,
                relatedClass: NestedCategory::class,
                foreignKey: 'categoryId',
            ),
        ],
    );
}

function makeNestedTagMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedTag::class,
        tableName: 'nested_tags',
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
            'commentId' => new PropertyMetadata(
                name: 'commentId',
                columnName: 'comment_id',
                type: 'int',
            ),
            'name' => new PropertyMetadata(
                name: 'name',
                columnName: 'name',
                type: 'string',
            ),
        ],
    );
}

function makeNestedCommentWithTagsMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NestedCommentWithTags::class,
        tableName: 'nested_comments_with_tags',
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
            'authorId' => new PropertyMetadata(
                name: 'authorId',
                columnName: 'author_id',
                type: 'int',
            ),
            'body' => new PropertyMetadata(
                name: 'body',
                columnName: 'body',
                type: 'string',
            ),
        ],
        relationships: [
            'author' => new RelationshipMetadata(
                propertyName: 'author',
                type: RelationshipType::BelongsTo,
                relatedClass: NestedAuthor::class,
                foreignKey: 'authorId',
            ),
            'tags' => new RelationshipMetadata(
                propertyName: 'tags',
                type: RelationshipType::HasMany,
                relatedClass: NestedTag::class,
                foreignKey: 'commentId',
            ),
        ],
    );
}

function makeNestedFakeQueryBuilder(array $rows): QueryBuilderInterface
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

function makeNestedLoader(array $metadataMap, array $responseQueue): RelationshipLoader
{
    $metadataFactory = new class ($metadataMap) extends EntityMetadataFactory
    {
        public function __construct(
            private array $metadataMap,
        ) {}

        public function parse(string $entityClass): EntityMetadata
        {
            return $this->metadataMap[$entityClass];
        }
    };

    $factory = new class ($responseQueue) implements QueryBuilderFactoryInterface
    {
        private int $callCount = 0;

        public function __construct(
            private array $responseQueue,
        ) {}

        public function create(): QueryBuilderInterface
        {
            $rows = $this->responseQueue[$this->callCount] ?? [];
            $this->callCount++;

            return makeNestedFakeQueryBuilder($rows);
        }
    };

    return new RelationshipLoader(
        entityMetadataFactory: $metadataFactory,
        entityHydrator: new EntityHydrator(),
        queryBuilderFactory: $factory,
    );
}

// ── Dot Notation Parsing ───────────────────────────────────────────────────────

it('parses single-level relationship name', function (): void {
    $tree = RelationshipLoader::parseRelationshipTree(['comments']);

    expect($tree)->toBe(['comments' => []]);
});

it('parses two-level dot notation into parent and child', function (): void {
    $tree = RelationshipLoader::parseRelationshipTree(['comments.author']);

    expect($tree)->toBe(['comments' => ['author' => []]]);
});

it('parses three-level dot notation', function (): void {
    $tree = RelationshipLoader::parseRelationshipTree(['comments.author.profile']);

    expect($tree)->toBe(['comments' => ['author' => ['profile' => []]]]);
});

it('merges duplicate parent relationships from multiple dot paths', function (): void {
    $tree = RelationshipLoader::parseRelationshipTree(['comments.author', 'comments.tags', 'category']);

    expect($tree)->toBe([
        'comments' => ['author' => [], 'tags' => []],
        'category' => [],
    ]);
});

// ── Nested Loading ─────────────────────────────────────────────────────────────

it('loads nested BelongsTo on HasMany results', function (): void {
    $post = new NestedPost();
    $post->id = 1;
    $post->title = 'Post One';

    $postMeta = makeNestedPostMetadata();
    $commentMeta = makeNestedCommentMetadata();
    $authorMeta = makeNestedAuthorMetadata();

    $metadataMap = [
        NestedPost::class => $postMeta,
        NestedComment::class => $commentMeta,
        NestedAuthor::class => $authorMeta,
    ];

    // Query 1: comments WHERE post_id IN (1) -> 2 comments
    // Query 2: authors WHERE id IN (10, 20) -> 2 authors
    $responseQueue = [
        [
            ['id' => 100, 'post_id' => 1, 'author_id' => 10, 'body' => 'First comment'],
            ['id' => 101, 'post_id' => 1, 'author_id' => 20, 'body' => 'Second comment'],
        ],
        [
            ['id' => 10, 'name' => 'Alice'],
            ['id' => 20, 'name' => 'Bob'],
        ],
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['comments' => ['author' => []]];
    $loader->loadNested([$post], $tree, $postMeta);

    expect($post->comments)->toHaveCount(2)
        ->and($post->comments[0]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($post->comments[0]->author->name)->toBe('Alice')
        ->and($post->comments[1]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($post->comments[1]->author->name)->toBe('Bob');
});

it('loads nested HasMany on BelongsTo result', function (): void {
    $post = new NestedPost();
    $post->id = 1;
    $post->categoryId = 5;
    $post->title = 'Post One';

    $postMeta = makeNestedPostMetadata();
    $categoryMeta = makeNestedCategoryMetadata();

    $metadataMap = [
        NestedPost::class => $postMeta,
        NestedCategory::class => $categoryMeta,
    ];

    // Query 1: categories WHERE id IN (5) -> 1 category
    $responseQueue = [
        [
            ['id' => 5, 'title' => 'Tech'],
        ],
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['category' => []];
    $loader->loadNested([$post], $tree, $postMeta);

    expect($post->category)->toBeInstanceOf(NestedCategory::class)
        ->and($post->category->title)->toBe('Tech');
});

it('loads multiple nested relationships on same parent', function (): void {
    $post = new NestedPost();
    $post->id = 1;
    $post->categoryId = 5;
    $post->title = 'Post One';

    $postMeta = makeNestedPostMetadata();
    $commentMeta = makeNestedCommentMetadata();
    $authorMeta = makeNestedAuthorMetadata();
    $categoryMeta = makeNestedCategoryMetadata();

    $metadataMap = [
        NestedPost::class => $postMeta,
        NestedComment::class => $commentMeta,
        NestedAuthor::class => $authorMeta,
        NestedCategory::class => $categoryMeta,
    ];

    // Query 1: comments WHERE post_id IN (1)
    // Query 2: authors WHERE id IN (10)
    // Query 3: categories WHERE id IN (5)
    $responseQueue = [
        [
            ['id' => 100, 'post_id' => 1, 'author_id' => 10, 'body' => 'A comment'],
        ],
        [
            ['id' => 10, 'name' => 'Alice'],
        ],
        [
            ['id' => 5, 'title' => 'Tech'],
        ],
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['comments' => ['author' => []], 'category' => []];
    $loader->loadNested([$post], $tree, $postMeta);

    expect($post->comments)->toHaveCount(1)
        ->and($post->comments[0]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($post->comments[0]->author->name)->toBe('Alice')
        ->and($post->category)->toBeInstanceOf(NestedCategory::class)
        ->and($post->category->title)->toBe('Tech');
});

it('loads three levels deep', function (): void {
    // We need a 3rd level: Post -> Comments -> Author -> (some relationship)
    // Use: Post hasMany Comments, Comment belongsTo Author, Author hasMany Tags
    // For simplicity, re-use NestedTag as "author tags" with authorId FK

    // Create a custom Author with tags property
    $post = new NestedPost();
    $post->id = 1;
    $post->title = 'Deep Post';

    // Author with tags
    $authorWithTagsClass = new class () extends Entity
    {
        /** @noinspection PhpUnused */
        #[Column(primaryKey: true, autoIncrement: true)]
        public ?int $id = null;

        /** @noinspection PhpUnused */
        #[Column]
        public string $name = '';

        /** @var NestedTag[] */
        public array $tags = [];
    };

    // We need a different approach - use the existing fixtures but build metadata manually
    // Let's use NestedCommentWithTags as the intermediate entity that has an author + tags

    // Post -> NestedCommentWithTags (hasMany) -> NestedAuthor (belongsTo)
    // This is 2-level. For 3 levels we need author to have something else.
    // Let's construct: posts -> comments -> author, and author has a "profile" via another class

    // Actually let's keep it simpler and build a PostWithTaggedComments scenario:
    // Post hasMany NestedCommentWithTags, NestedCommentWithTags hasMany NestedTag
    $post2 = new NestedPost();
    $post2->id = 2;
    $post2->title = 'Post Two';

    // Build metadata that allows 3 levels: post -> commentWithTags -> tag
    // But NestedPost only has 'comments' (NestedComment) and 'category' relationships
    // We need a post-like entity with NestedCommentWithTags as its HasMany

    // Create an ad-hoc post entity for this test
    $postWithTaggedComments = new class () extends Entity
    {
        /** @noinspection PhpUnused */
        #[Column(primaryKey: true, autoIncrement: true)]
        public ?int $id = null;

        /** @noinspection PhpUnused */
        #[Column]
        public string $title = '';

        /** @var NestedCommentWithTags[] */
        public array $comments = [];
    };

    $postClass = $postWithTaggedComments::class;

    $postWithTaggedCommentsMeta = new EntityMetadata(
        entityClass: $postClass,
        tableName: 'nested_posts',
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
        relationships: [
            'comments' => new RelationshipMetadata(
                propertyName: 'comments',
                type: RelationshipType::HasMany,
                relatedClass: NestedCommentWithTags::class,
                foreignKey: 'postId',
            ),
        ],
    );

    $postWithTaggedComments->id = 1;

    $commentWithTagsMeta = makeNestedCommentWithTagsMetadata();
    $authorMeta = makeNestedAuthorMetadata();
    $tagMeta = makeNestedTagMetadata();

    $metadataMap = [
        $postClass => $postWithTaggedCommentsMeta,
        NestedCommentWithTags::class => $commentWithTagsMeta,
        NestedAuthor::class => $authorMeta,
        NestedTag::class => $tagMeta,
    ];

    // Query 1: comments WHERE post_id IN (1)
    // Query 2: authors WHERE id IN (10)
    // Query 3: tags WHERE comment_id IN (100)
    $responseQueue = [
        [
            ['id' => 100, 'post_id' => 1, 'author_id' => 10, 'body' => 'Comment body'],
        ],
        [
            ['id' => 10, 'name' => 'Alice'],
        ],
        [
            ['id' => 200, 'comment_id' => 100, 'name' => 'php'],
            ['id' => 201, 'comment_id' => 100, 'name' => 'laravel'],
        ],
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['comments' => ['author' => [], 'tags' => []]];
    $loader->loadNested([$postWithTaggedComments], $tree, $postWithTaggedCommentsMeta);

    expect($postWithTaggedComments->comments)->toHaveCount(1)
        ->and($postWithTaggedComments->comments[0]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($postWithTaggedComments->comments[0]->author->name)->toBe('Alice')
        ->and($postWithTaggedComments->comments[0]->tags)->toHaveCount(2)
        ->and($postWithTaggedComments->comments[0]->tags[0]->name)->toBe('php');
});

// ── Batch Optimization ─────────────────────────────────────────────────────────

it('batch loads nested relationships across all parent entities', function (): void {
    $post1 = new NestedPost();
    $post1->id = 1;
    $post1->title = 'Post One';

    $post2 = new NestedPost();
    $post2->id = 2;
    $post2->title = 'Post Two';

    $postMeta = makeNestedPostMetadata();
    $commentMeta = makeNestedCommentMetadata();
    $authorMeta = makeNestedAuthorMetadata();

    $metadataMap = [
        NestedPost::class => $postMeta,
        NestedComment::class => $commentMeta,
        NestedAuthor::class => $authorMeta,
    ];

    // Query 1: comments WHERE post_id IN (1, 2) -> comments for both posts
    // Query 2: authors WHERE id IN (10, 20) -> single batch for all comments' authors
    $responseQueue = [
        [
            ['id' => 100, 'post_id' => 1, 'author_id' => 10, 'body' => 'Comment A'],
            ['id' => 101, 'post_id' => 2, 'author_id' => 20, 'body' => 'Comment B'],
        ],
        [
            ['id' => 10, 'name' => 'Alice'],
            ['id' => 20, 'name' => 'Bob'],
        ],
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['comments' => ['author' => []]];
    $loader->loadNested([$post1, $post2], $tree, $postMeta);

    expect($post1->comments)->toHaveCount(1)
        ->and($post1->comments[0]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($post1->comments[0]->author->name)->toBe('Alice')
        ->and($post2->comments)->toHaveCount(1)
        ->and($post2->comments[0]->author)->toBeInstanceOf(NestedAuthor::class)
        ->and($post2->comments[0]->author->name)->toBe('Bob');
});

it('handles empty intermediate results without error', function (): void {
    $post = new NestedPost();
    $post->id = 1;
    $post->title = 'Empty Post';

    $postMeta = makeNestedPostMetadata();
    $commentMeta = makeNestedCommentMetadata();
    $authorMeta = makeNestedAuthorMetadata();

    $metadataMap = [
        NestedPost::class => $postMeta,
        NestedComment::class => $commentMeta,
        NestedAuthor::class => $authorMeta,
    ];

    // Query 1: comments WHERE post_id IN (1) -> no comments
    // Query 2 should NOT run (no comments to load authors for)
    $responseQueue = [
        [], // no comments
    ];

    $loader = makeNestedLoader($metadataMap, $responseQueue);
    $tree = ['comments' => ['author' => []]];
    $loader->loadNested([$post], $tree, $postMeta);

    expect($post->comments)->toBe([]);
});
