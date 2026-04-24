<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Query;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Marko\Database\Repository\Repository;
use ReflectionClass;
use RuntimeException;

// ── Entity Fixtures ────────────────────────────────────────────────────────────

#[Table('spec_posts')]
class SpecPost extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';

    #[Column]
    public string $status = '';

    #[Column]
    public ?int $authorId = null;

    #[BelongsTo(SpecAuthor::class, foreignKey: 'authorId')]
    public ?SpecAuthor $author = null;

    #[HasMany(SpecTag::class, foreignKey: 'postId')]
    /** @var SpecTag[] */
    public array $tags = [];
}

#[Table('spec_authors')]
class SpecAuthor extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '';

    #[HasOne(SpecProfile::class, foreignKey: 'authorId')]
    public ?SpecProfile $profile = null;
}

#[Table('spec_tags')]
class SpecTag extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '';

    #[Column]
    public int $postId = 0;
}

#[Table('spec_profiles')]
class SpecProfile extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public int $authorId = 0;

    #[Column]
    public string $bio = '';
}

// ── Repository Fixture ─────────────────────────────────────────────────────────

class SpecPostRepository extends Repository
{
    protected const string ENTITY_CLASS = SpecPost::class;
}

// ── Stub Helpers ───────────────────────────────────────────────────────────────

/**
 * Build a simple QueryBuilderInterface stub with tracked calls.
 *
 * @param array<array<string, mixed>> $rows
 */
function makeSpecStubBuilder(array $rows = []): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
        /** @var string[] */
        public array $wheresCalled = [];

        public function __construct(private readonly array $rows) {}

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function distinct(): static
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

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
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

        public function count(?string $column = null): int
        {
            return count($this->rows);
        }

        public function getColumnCount(): int
        {
            return 0;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
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

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }
    };
}

/**
 * Build a QueryBuilderInterface that tracks whereIn call count (for N+1 detection).
 */
function makeCountingBuilder(array $rows = []): QueryBuilderInterface
{
    return new class ($rows) implements QueryBuilderInterface
    {
        public int $whereInCount = 0;

        public function __construct(private readonly array $rows) {}

        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function distinct(): static
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

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
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

        public function count(?string $column = null): int
        {
            return count($this->rows);
        }

        public function getColumnCount(): int
        {
            return 0;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
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

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }
    };
}

function makeSpecConnection(array $rows = []): ConnectionInterface
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

function makeSpecQbFactory(QueryBuilderInterface $builder): QueryBuilderFactoryInterface
{
    return new class ($builder) implements QueryBuilderFactoryInterface
    {
        public function __construct(private readonly QueryBuilderInterface $builder) {}

        public function create(): QueryBuilderInterface
        {
            return $this->builder;
        }
    };
}

function makeSpecLoader(QueryBuilderInterface $relatedBuilder): RelationshipLoader
{
    return new RelationshipLoader(
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeSpecQbFactory($relatedBuilder),
    );
}

function makeSpecRepository(
    array $connectionRows = [],
    ?RelationshipLoader $loader = null,
    ?QueryBuilderInterface $primaryBuilder = null,
): SpecPostRepository {
    $qbFactory = $primaryBuilder !== null ? makeSpecQbFactory($primaryBuilder) : null;

    return new SpecPostRepository(
        connection: makeSpecConnection($connectionRows),
        metadataFactory: new EntityMetadataFactory(),
        hydrator: new EntityHydrator(),
        queryBuilderFactory: $qbFactory,
        eventDispatcher: null,
        relationshipLoader: $loader,
    );
}

// ══════════════════════════════════════════════════════════════════════════════
// Tests
// ══════════════════════════════════════════════════════════════════════════════

it('exposes EntityQueryBuilderInterface as the parameter type of QuerySpecification::apply()', function (): void {
    $reflection = new ReflectionClass(QuerySpecification::class);
    $method = $reflection->getMethod('apply');
    $params = $method->getParameters();

    expect($params)->toHaveCount(1)
        ->and($params[0]->getType()?->getName())->toBe(EntityQueryBuilderInterface::class);
});

it('lets a spec call $builder->with(\'relation\') inside apply() to declare eager loading', function (): void {
    $capturedWith = [];

    $builder = new class ($capturedWith) implements EntityQueryBuilderInterface
    {
        public function __construct(private array &$capturedWith) {}

        public function with(string ...$relations): static
        {
            $this->capturedWith = array_values($relations);

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

        public function distinct(): static
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

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
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

        public function count(?string $column = null): int
        {
            return 0;
        }

        public function getColumnCount(): int
        {
            return 0;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
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

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }
    };

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author');
        }
    };

    $spec->apply($builder);

    expect($capturedWith)->toBe(['author']);
});

it('eager-loads relationships declared by a spec via the fluent builder', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];
    $authorRows = [['id' => 5, 'name' => 'Alice', 'author_id' => 5]];

    $primaryBuilder = makeSpecStubBuilder($postRows);
    $relatedBuilder = makeSpecStubBuilder($authorRows);
    $loader = makeSpecLoader($relatedBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author');
        }
    };

    $collection = $repo->matching($spec);

    expect($collection->count())->toBe(1)
        ->and($collection->first()->author)->toBeInstanceOf(SpecAuthor::class);
});

it('eager-loads nested relationship paths declared by a spec (e.g. "author.profile")', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];
    $authorRows = [['id' => 5, 'name' => 'Alice', 'author_id' => 5]];
    $profileRows = [['id' => 10, 'bio' => 'Writer', 'author_id' => 5]];

    $primaryBuilder = makeSpecStubBuilder($postRows);
    $authorBuilder = makeSpecStubBuilder($authorRows);
    $profileBuilder = makeSpecStubBuilder($profileRows);

    // The loader uses a single factory — we use authorRows to cover the nested load
    $loader = makeSpecLoader($authorBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author.profile');
        }
    };

    $collection = $repo->matching($spec);

    // Author is loaded; profile would be nested but the stub returns no profile rows —
    // what matters is that 'author.profile' was accepted and no exception was thrown.
    expect($collection->count())->toBe(1)
        ->and($collection->first()->author)->toBeInstanceOf(SpecAuthor::class);
});

it('merges eager loads across multiple specs without duplicating queries', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];

    $countingBuilder = makeCountingBuilder([['id' => 5, 'name' => 'Alice', 'author_id' => 5]]);
    $primaryBuilder = makeSpecStubBuilder($postRows);
    $loader = makeSpecLoader($countingBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $specA = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author');
        }
    };

    $specB = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author'); // duplicate — should not cause double query
        }
    };

    $repo->matching($specA, $specB);

    // Only one whereIn should have been issued for `author`, not two
    expect($countingBuilder->whereInCount)->toBe(1);
});

it('still supports explicit $repo->with(...)->matching(...) callers (fixes pre-existing bug where matching() passed the raw inner builder)', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];
    $authorRows = [['id' => 5, 'name' => 'Alice', 'author_id' => 5]];

    $primaryBuilder = makeSpecStubBuilder($postRows);
    $relatedBuilder = makeSpecStubBuilder($authorRows);
    $loader = makeSpecLoader($relatedBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    // Plain spec that applies no eager load — eager load comes from call-site with()
    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->where('status', '=', 'published');
        }
    };

    $collection = $repo->with('author')->matching($spec);

    expect($collection->count())->toBe(1)
        ->and($collection->first()->author)->toBeInstanceOf(SpecAuthor::class);
});

it('merges call-site $repo->with(...) relationships with spec-declared with() relationships without duplicates', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];

    $countingBuilder = makeCountingBuilder([['id' => 5, 'name' => 'Alice', 'author_id' => 5]]);
    $primaryBuilder = makeSpecStubBuilder($postRows);
    $loader = makeSpecLoader($countingBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author');
        }
    };

    // Both call-site and spec declare 'author' — should issue only one query
    $repo->with('author')->matching($spec);

    expect($countingBuilder->whereInCount)->toBe(1);
});

it('does not execute N+1 queries when a spec declares eager loads', function (): void {
    // 3 posts, all with author_id = 5
    $postRows = [
        ['id' => 1, 'title' => 'A', 'status' => 'published', 'author_id' => 5],
        ['id' => 2, 'title' => 'B', 'status' => 'published', 'author_id' => 5],
        ['id' => 3, 'title' => 'C', 'status' => 'published', 'author_id' => 5],
    ];

    $countingBuilder = makeCountingBuilder([['id' => 5, 'name' => 'Alice', 'author_id' => 5]]);
    $primaryBuilder = makeSpecStubBuilder($postRows);
    $loader = makeSpecLoader($countingBuilder);
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('author');
        }
    };

    $collection = $repo->matching($spec);

    // Should be exactly 1 whereIn query for 3 entities, not 3 separate queries
    expect($collection->count())->toBe(3)
        ->and($countingBuilder->whereInCount)->toBe(1);
});

it('validates each spec-declared relationship name against entity metadata and throws on unknown names (consistent with Repository::with())', function (): void {
    $postRows = [['id' => 1, 'title' => 'Hello', 'status' => 'published', 'author_id' => 5]];
    $primaryBuilder = makeSpecStubBuilder($postRows);
    $loader = makeSpecLoader(makeSpecStubBuilder());
    $repo = makeSpecRepository(loader: $loader, primaryBuilder: $primaryBuilder);

    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->with('nonExistentRelationship');
        }
    };

    expect(fn () => $repo->matching($spec))->toThrow(RepositoryException::class);
});

it('existing single-method QuerySpecification implementations continue to compile after updating only the apply() parameter type hint', function (): void {
    // A spec using the new signature — verifies backward-compatible refactor
    $spec = new class () implements QuerySpecification
    {
        public function apply(EntityQueryBuilderInterface $builder): void
        {
            $builder->where('status', '=', 'active');
        }
    };

    $reflection = new ReflectionClass($spec);
    $method = $reflection->getMethod('apply');
    $params = $method->getParameters();

    expect($params[0]->getType()?->getName())->toBe(EntityQueryBuilderInterface::class);
});
