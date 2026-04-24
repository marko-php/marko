<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Repository;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
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
use Marko\Database\Repository\Repository;
use RuntimeException;
use TypeError;

// ── Fixtures ───────────────────────────────────────────────────────────────────

#[Table('int_pk_items')]
class IntPkItem extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $label = '';
}

class IntPkItemRepository extends Repository
{
    protected const string ENTITY_CLASS = IntPkItem::class;
}

#[Table('products')]
class StringPkProduct extends Entity
{
    #[Column(primaryKey: true)]
    public ?string $uuid = null;

    #[Column]
    public string $name = '';
}

class StringPkProductRepository extends Repository
{
    protected const string ENTITY_CLASS = StringPkProduct::class;
}

#[Table('orders')]
class StringPkOrder extends Entity
{
    #[Column(primaryKey: true)]
    public ?string $uuid = null;

    #[Column]
    public string $status = '';

    #[Column]
    public ?string $productUuid = null;

    public ?StringPkProduct $product = null;

    /** @var StringPkOrderLine[] */
    public array $lines = [];
}

class StringPkOrderRepository extends Repository
{
    protected const string ENTITY_CLASS = StringPkOrder::class;
}

#[Table('order_lines')]
class StringPkOrderLine extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public ?string $orderUuid = null;

    #[Column]
    public string $item = '';
}

class StringPkOrderLineRepository extends Repository
{
    protected const string ENTITY_CLASS = StringPkOrderLine::class;
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeStringPkConnection(array $queryResults = [], array &$executedSql = [], array &$executedBindings = []): ConnectionInterface
{
    return new class ($queryResults, $executedSql, $executedBindings) implements ConnectionInterface
    {
        private int $queryIndex = 0;

        public function __construct(
            private array $queryResults,
            private array &$executedSql,
            private array &$executedBindings,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(string $sql, array $bindings = []): array
        {
            $result = $this->queryResults[$this->queryIndex] ?? [];
            $this->queryIndex++;

            return $result;
        }

        public function execute(string $sql, array $bindings = []): int
        {
            $this->executedSql[] = $sql;
            $this->executedBindings[] = $bindings;

            return 1;
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

function makeStringPkQueryBuilder(array $rows, array &$capturedWhereIn = []): QueryBuilderInterface
{
    return new class ($rows, $capturedWhereIn) implements QueryBuilderInterface
    {
        public function __construct(
            private array $rows,
            private array &$capturedWhereIn,
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
            $this->capturedWhereIn[] = ['column' => $column, 'values' => $values];

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
            return 1;
        }

        public function update(array $data): int
        {
            return 1;
        }

        public function delete(): int
        {
            return 1;
        }

        public function count(?string $column = null): int
        {
            return 0;
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

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function raw(string $sql, array $bindings = []): array
        {
            return [];
        }
    };
}

function makeStringPkQueryBuilderFactory(array $rowSets, array &$capturedWhereIn = []): QueryBuilderFactoryInterface
{
    return new class ($rowSets, $capturedWhereIn) implements QueryBuilderFactoryInterface
    {
        private int $index = 0;

        public function __construct(
            private array $rowSets,
            private array &$capturedWhereIn,
        ) {}

        public function create(): QueryBuilderInterface
        {
            $rows = $this->rowSets[$this->index] ?? [];
            $this->index++;

            return makeStringPkQueryBuilder($rows, $this->capturedWhereIn);
        }
    };
}

// ── Tests ──────────────────────────────────────────────────────────────────────

it('finds an entity by string primary key', function (): void {
    $uuid = 'abc-123-uuid';
    $connection = makeStringPkConnection([[
        ['uuid' => $uuid, 'name' => 'Widget'],
    ]]);
    $repository = new StringPkProductRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $product = $repository->find($uuid);

    expect($product)->toBeInstanceOf(StringPkProduct::class)
        ->and($product->uuid)->toBe($uuid)
        ->and($product->name)->toBe('Widget');
});

it('saves a new entity with a string primary key', function (): void {
    $sql = [];
    $bindings = [];
    $connection = makeStringPkConnection([], $sql, $bindings);
    $repository = new StringPkProductRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $product = new StringPkProduct();
    $product->uuid = 'new-uuid-001';
    $product->name = 'Gadget';

    $repository->save($product);

    expect($sql)->toHaveCount(1)
        ->and($sql[0])->toContain('INSERT INTO products')
        ->and($bindings[0])->toContain('new-uuid-001')
        ->and($bindings[0])->toContain('Gadget');
});

it('updates an existing entity with a string primary key via dirty tracking', function (): void {
    $uuid = 'update-uuid-001';
    $sql = [];
    $bindings = [];
    $connection = makeStringPkConnection(
        [[['uuid' => $uuid, 'name' => 'Original']]],
        $sql,
        $bindings,
    );
    $repository = new StringPkProductRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $product = $repository->find($uuid);
    $product->name = 'Updated';
    $repository->save($product);

    expect($sql)->toHaveCount(1)
        ->and($sql[0])->toContain('UPDATE products')
        ->and($sql[0])->toContain('WHERE uuid = ?')
        ->and($bindings[0])->toContain($uuid);
});

it('deletes an entity with a string primary key', function (): void {
    $uuid = 'delete-uuid-001';
    $sql = [];
    $bindings = [];
    $connection = makeStringPkConnection(
        [[['uuid' => $uuid, 'name' => 'ToDelete']]],
        $sql,
        $bindings,
    );
    $repository = new StringPkProductRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $product = $repository->find($uuid);
    $repository->delete($product);

    expect($sql)->toHaveCount(1)
        ->and($sql[0])->toContain('DELETE FROM products')
        ->and($bindings[0])->toContain($uuid);
});

it('loads a BelongsTo relationship when the foreign key is a string', function (): void {
    $productUuid = 'prod-uuid-001';
    $orderUuid = 'order-uuid-001';

    $capturedWhereIn = [];
    $queryBuilderFactory = makeStringPkQueryBuilderFactory(
        [
            // Query for the product (BelongsTo: WHERE pk IN (fk_values))
            [['uuid' => $productUuid, 'name' => 'Widget']],
        ],
        $capturedWhereIn,
    );

    $hydrator = new EntityHydrator();
    $metadataFactory = new EntityMetadataFactory();

    // Build order entity with productUuid set
    $order = new StringPkOrder();
    $order->uuid = $orderUuid;
    $order->status = 'pending';
    $order->productUuid = $productUuid;
    $hydrator->registerOriginalValues($order, $metadataFactory->parse(StringPkOrder::class));

    $orderMetadata = new EntityMetadata(
        entityClass: StringPkOrder::class,
        tableName: 'orders',
        primaryKey: 'uuid',
        properties: [
            'uuid' => new PropertyMetadata(name: 'uuid', columnName: 'uuid', type: 'string', nullable: true, isPrimaryKey: true, isAutoIncrement: false),
            'status' => new PropertyMetadata(name: 'status', columnName: 'status', type: 'string'),
            'productUuid' => new PropertyMetadata(name: 'productUuid', columnName: 'product_uuid', type: 'string', nullable: true),
        ],
        relationships: [
            'product' => new RelationshipMetadata(
                type: RelationshipType::BelongsTo,
                propertyName: 'product',
                relatedClass: StringPkProduct::class,
                foreignKey: 'productUuid',
            ),
        ],
    );

    $loader = new RelationshipLoader($metadataFactory, $hydrator, $queryBuilderFactory);
    $relationship = $orderMetadata->getRelationship('product');
    $loader->load([$order], $relationship, $orderMetadata);

    expect($order->product)->toBeInstanceOf(StringPkProduct::class)
        ->and($order->product->uuid)->toBe($productUuid)
        ->and($capturedWhereIn[0]['values'])->toBe([$productUuid]);
});

it('loads a HasMany relationship when the parent primary key is a string', function (): void {
    $orderUuid = 'order-uuid-002';

    $capturedWhereIn = [];
    $queryBuilderFactory = makeStringPkQueryBuilderFactory(
        [
            // Query for order lines (HasMany: WHERE fk IN (parent_pk_values))
            [
                ['id' => 1, 'order_uuid' => $orderUuid, 'item' => 'Line Item 1'],
                ['id' => 2, 'order_uuid' => $orderUuid, 'item' => 'Line Item 2'],
            ],
        ],
        $capturedWhereIn,
    );

    $hydrator = new EntityHydrator();
    $metadataFactory = new EntityMetadataFactory();

    $order = new StringPkOrder();
    $order->uuid = $orderUuid;
    $order->status = 'pending';
    $order->productUuid = null;

    $orderMetadata = new EntityMetadata(
        entityClass: StringPkOrder::class,
        tableName: 'orders',
        primaryKey: 'uuid',
        properties: [
            'uuid' => new PropertyMetadata(name: 'uuid', columnName: 'uuid', type: 'string', nullable: true, isPrimaryKey: true, isAutoIncrement: false),
            'status' => new PropertyMetadata(name: 'status', columnName: 'status', type: 'string'),
            'productUuid' => new PropertyMetadata(name: 'productUuid', columnName: 'product_uuid', type: 'string', nullable: true),
        ],
        relationships: [
            'lines' => new RelationshipMetadata(
                type: RelationshipType::HasMany,
                propertyName: 'lines',
                relatedClass: StringPkOrderLine::class,
                foreignKey: 'orderUuid',
            ),
        ],
    );

    $loader = new RelationshipLoader($metadataFactory, $hydrator, $queryBuilderFactory);
    $relationship = $orderMetadata->getRelationship('lines');
    $loader->load([$order], $relationship, $orderMetadata);

    expect($order->lines)->toHaveCount(2)
        ->and($order->lines[0])->toBeInstanceOf(StringPkOrderLine::class)
        ->and($capturedWhereIn[0]['values'])->toBe([$orderUuid]);
});

it('batches WHERE IN queries correctly for string foreign keys without SQL injection', function (): void {
    $uuids = ["uuid-1'; DROP TABLE orders;--", 'uuid-2'];

    $capturedWhereIn = [];
    $queryBuilderFactory = makeStringPkQueryBuilderFactory(
        [
            // Query for order lines (HasMany: WHERE fk IN (parent_pk_values))
            [
                ['id' => 1, 'order_uuid' => $uuids[0], 'item' => 'Line 1'],
                ['id' => 2, 'order_uuid' => $uuids[1], 'item' => 'Line 2'],
            ],
        ],
        $capturedWhereIn,
    );

    $hydrator = new EntityHydrator();
    $metadataFactory = new EntityMetadataFactory();

    $order1 = new StringPkOrder();
    $order1->uuid = $uuids[0];
    $order1->status = 'pending';
    $order1->productUuid = null;

    $order2 = new StringPkOrder();
    $order2->uuid = $uuids[1];
    $order2->status = 'shipped';
    $order2->productUuid = null;

    $orderMetadata = new EntityMetadata(
        entityClass: StringPkOrder::class,
        tableName: 'orders',
        primaryKey: 'uuid',
        properties: [
            'uuid' => new PropertyMetadata(name: 'uuid', columnName: 'uuid', type: 'string', nullable: true, isPrimaryKey: true, isAutoIncrement: false),
            'status' => new PropertyMetadata(name: 'status', columnName: 'status', type: 'string'),
            'productUuid' => new PropertyMetadata(name: 'productUuid', columnName: 'product_uuid', type: 'string', nullable: true),
        ],
        relationships: [
            'lines' => new RelationshipMetadata(
                type: RelationshipType::HasMany,
                propertyName: 'lines',
                relatedClass: StringPkOrderLine::class,
                foreignKey: 'orderUuid',
            ),
        ],
    );

    $loader = new RelationshipLoader($metadataFactory, $hydrator, $queryBuilderFactory);
    $relationship = $orderMetadata->getRelationship('lines');
    $loader->load([$order1, $order2], $relationship, $orderMetadata);

    // The WHERE IN values are passed as parameterized bindings, not interpolated into SQL
    expect($capturedWhereIn[0]['values'])->toBe($uuids);
});

it('still supports integer primary keys with identical behavior', function (): void {
    $sql = [];
    $bindings = [];
    $connection = makeStringPkConnection(
        [[['id' => 42, 'label' => 'Widget']]],
        $sql,
        $bindings,
    );
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();
    $repository = new IntPkItemRepository($connection, $metadataFactory, $hydrator);

    $item = $repository->find(42);

    expect($item)->toBeInstanceOf(IntPkItem::class)
        ->and($item->id)->toBe(42)
        ->and($item->label)->toBe('Widget');
});

it('rejects non int/string primary key values with a descriptive exception', function (): void {
    $sql = [];
    $bindings = [];
    $connection = makeStringPkConnection([], $sql, $bindings);
    $repository = new StringPkProductRepository($connection, new EntityMetadataFactory(), new EntityHydrator());

    $repository->find(3.14);
})->throws(TypeError::class);
