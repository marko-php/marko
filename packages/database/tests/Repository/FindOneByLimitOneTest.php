<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Repository\Repository;
use RuntimeException;

// ── Entity Fixtures ────────────────────────────────────────────────────────────

#[Table('fob_users')]
class FobUser extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $email = '';

    /** @noinspection PhpUnused */
    #[Column]
    public string $status = '';

    #[HasOne(FobProfile::class, foreignKey: 'userId')]
    public ?FobProfile $profile = null;
}

#[Table('fob_profiles')]
class FobProfile extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public int $userId = 0;
}

// ── Repository Fixture ─────────────────────────────────────────────────────────

class FobUserRepository extends Repository
{
    protected const string ENTITY_CLASS = FobUser::class;
}

// ── Stub Helpers ───────────────────────────────────────────────────────────────

/**
 * Build a simple read-only connection returning fixed rows for any query.
 *
 * @param array<array<string, mixed>> $rows
 */
function makeFobConnection(array $rows = []): ConnectionInterface
{
    return new readonly class ($rows) implements ConnectionInterface
    {
        public function __construct(
            private array $rows,
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
            return $this->rows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
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

        public function driverName(): string
        {
            return 'sqlite';
        }
    };
}

/**
 * Build a query-recording connection that stores every query() call.
 *
 * @param array<array<string, mixed>> $rows Rows to return for every query() call
 * @param array<array{sql: string, bindings: array<mixed>}> $queries Reference to the external capture array
 */
function makeFobRecordingConnection(array $rows, array &$queries): ConnectionInterface
{
    return new class ($rows, $queries) implements ConnectionInterface
    {
        public function __construct(
            private readonly array $rows,
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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
            $this->queries[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->rows;
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
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

        public function driverName(): string
        {
            return 'sqlite';
        }
    };
}

function makeFobQbFactory(array $relatedRows = []): QueryBuilderFactoryInterface
{
    $stub = new readonly class ($relatedRows) implements QueryBuilderInterface
    {
        public function __construct(private array $rows) {}

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

        public function whereJsonContains(
            string $path,
            mixed $value,
        ): static {
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

        public function orderByRaw(
            string $expression,
            string $direction = 'ASC',
        ): static {
            return $this;
        }

        public function selectRaw(
            string $expression,
            array $bindings = [],
        ): static {
            return $this;
        }

        public function whereRaw(
            string $expression,
            array $bindings = [],
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

        public function insert(
            array $data,
            ?string $primaryKey = null,
        ): int {
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

        public function having(
            string $expression,
            array $bindings = [],
        ): static {
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

    return new readonly class ($stub) implements QueryBuilderFactoryInterface
    {
        public function __construct(private QueryBuilderInterface $builder) {}

        public function create(): QueryBuilderInterface
        {
            return $this->builder;
        }
    };
}

function makeFobLoader(array $relatedRows = []): RelationshipLoader
{
    return new RelationshipLoader(
        new EntityMetadataFactory(),
        new EntityHydrator(),
        makeFobQbFactory($relatedRows),
    );
}

// ── Tests ──────────────────────────────────────────────────────────────────────

describe('findOneBy LIMIT 1', function (): void {
    it('returns the matching entity for findOneBy when a row exists', function (): void {
        $row = ['id' => 7, 'email' => 'alice@example.com', 'status' => 'active'];
        $connection = makeFobConnection([$row]);
        $repo = new FobUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        /** @var FobUser $entity */
        $entity = $repo->findOneBy(['email' => 'alice@example.com']);

        expect($entity)->toBeInstanceOf(FobUser::class)
            ->and($entity->id)->toBe(7)
            ->and($entity->email)->toBe('alice@example.com');
    });

    it('returns null from findOneBy when no row matches', function (): void {
        $connection = makeFobConnection([]);
        $repo = new FobUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $entity = $repo->findOneBy(['email' => 'ghost@example.com']);

        expect($entity)->toBeNull();
    });

    it('appends LIMIT 1 to the findOneBy query', function (): void {
        $queries = [];
        $row = ['id' => 1, 'email' => 'x@example.com', 'status' => 'active'];
        $connection = makeFobRecordingConnection([$row], $queries);
        $repo = new FobUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repo->findOneBy(['email' => 'x@example.com']);

        expect($queries)->toHaveCount(1)
            ->and($queries[0]['sql'])->toContain('LIMIT 1');
    });

    it('issues exactly one query for findOneBy even when multiple rows would match', function (): void {
        $queries = [];
        $rows = [
            ['id' => 1, 'email' => 'a@example.com', 'status' => 'active'],
            ['id' => 2, 'email' => 'b@example.com', 'status' => 'active'],
            ['id' => 3, 'email' => 'c@example.com', 'status' => 'active'],
        ];
        // Connection returns all 3 rows (simulating DB returning multiple), but the
        // SQL itself contains LIMIT 1 — one query is issued.
        $connection = makeFobRecordingConnection($rows, $queries);
        $repo = new FobUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        $repo->findOneBy(['status' => 'active']);

        expect($queries)->toHaveCount(1);
    });

    it('maps entity property names to column names in the findOneBy WHERE clause', function (): void {
        $queries = [];
        $row = ['id' => 5, 'email' => 'mapped@example.com', 'status' => 'pending'];
        $connection = makeFobRecordingConnection([$row], $queries);
        $repo = new FobUserRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

        // 'status' maps to column 'status'; verify the WHERE clause uses the column name
        $repo->findOneBy(['status' => 'pending']);

        expect($queries[0]['sql'])->toContain('status = ?')
            ->and($queries[0]['bindings'])->toBe(['pending']);
    });

    it('eager-loads relationships on the single entity returned by findOneBy', function (): void {
        $userRow = ['id' => 3, 'email' => 'rel@example.com', 'status' => 'active'];
        $profileRow = ['id' => 30, 'user_id' => 3];

        $factory = makeFobQbFactory([$profileRow]);
        $loader = new RelationshipLoader(new EntityMetadataFactory(), new EntityHydrator(), $factory);

        $repo = new FobUserRepository(
            connection: makeFobConnection([$userRow]),
            metadataFactory: new EntityMetadataFactory(),
            hydrator: new EntityHydrator(),
            queryBuilderFactory: $factory,
            eventDispatcher: null,
            relationshipLoader: $loader,
        );

        /** @var FobUser $entity */
        $entity = $repo->with('profile')->findOneBy(['email' => 'rel@example.com']);

        expect($entity)->toBeInstanceOf(FobUser::class)
            ->and($entity->profile)->toBeInstanceOf(FobProfile::class)
            ->and($entity->profile->id)->toBe(30);
    });
});
