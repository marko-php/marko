<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Repository\Repository;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

// Product entity fixture
#[Table('products')]
class Product extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $name;
}

// Companion for scoped overrides
#[Table(extends: Product::class)]
class ProductScopedOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}

// Repository for Product
class ProductOverridesRepository extends Repository
{
    protected const string ENTITY_CLASS = Product::class;
}

/**
 * Create a logging connection that records executed SQL and bindings.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeLoggingConnection(array &$sqlLog): ConnectionInterface
{
    return new class ($sqlLog) implements ConnectionInterface
    {
        private int $lastId = 0;

        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$sqlLog,
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
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->sqlLog[] = ['sql' => $sql, 'bindings' => $bindings];
            $this->lastId++;

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastId;
        }
    };
}

/**
 * Build a fresh repository with a new logging connection.
 *
 * @param array<array{sql: string, bindings: array<mixed>}> $sqlLog
 */
function makeRepository(array &$sqlLog): ProductOverridesRepository
{
    $connection = makeLoggingConnection($sqlLog);
    $metadataFactory = new EntityMetadataFactory();
    $metadataFactory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
    $hydrator = new EntityHydrator($metadataFactory);

    return new ProductOverridesRepository($connection, $metadataFactory, $hydrator);
}

it('dirty-tracks the override companion via Repository::update without a custom plugin', function (): void {
    $sqlLog = [];
    $repository = makeRepository($sqlLog);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    // INSERT
    $repository->save($product);

    // Mutate only overrides (no plugin involved — companion path)
    $sqlLog = [];
    $overrides->setOverride('locale:de', 'name', 'Hallo');
    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('UPDATE products')
        ->and($sqlLog[0]['sql'])->toContain('scopes');

    // No further mutation — re-save must produce no SQL
    $sqlLog = [];
    $repository->save($product);

    expect($sqlLog)->toBeEmpty();
});

it('round-trips overrides via save then re-hydrate via find', function (): void {
    $insertedScopes = null;
    $lastId = 0;

    $connection = new class ($insertedScopes, $lastId) implements ConnectionInterface
    {
        public function __construct(
            private mixed &$insertedScopes,
            private int &$lastId,
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
            // Simulate SELECT * FROM products WHERE id = ?
            if (str_contains($sql, 'WHERE id = ?')) {
                return [[
                    'id' => $this->lastId,
                    'name' => 'Shirt',
                    'scopes' => $this->insertedScopes,
                ]];
            }

            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if (str_starts_with($sql, 'INSERT')) {
                $this->lastId++;
                $this->insertedScopes = end($bindings);
            }

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return $this->lastId;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $metadataFactory->linkExtenders(Product::class, [ProductScopedOverrides::class]);
    $hydrator = new EntityHydrator($metadataFactory);
    $repository = new ProductOverridesRepository($connection, $metadataFactory, $hydrator);

    // Build and save a product with overrides
    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    $repository->save($product);

    // Re-hydrate via find
    /** @var Product $found */
    $found = $repository->find($product->id);

    /** @var ProductScopedOverrides $foundOverrides */
    $foundOverrides = $found->companion(ProductScopedOverrides::class);

    expect($found)->not->toBeNull()
        ->and($foundOverrides)->not->toBeNull()
        ->and($foundOverrides->getOverride('geo:eu.de', 'name'))->toBe('Hemd')
        ->and($foundOverrides->allOverrides())->toBe(['geo:eu.de' => ['name' => 'Hemd']]);
});

it('writes null into the scopes column when all overrides are cleared', function (): void {
    $sqlLog = [];
    $repository = makeRepository($sqlLog);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    // INSERT with overrides
    $repository->save($product);

    // Clear all overrides
    $sqlLog = [];
    $overrides->clearOverride('geo:eu.de', 'name');
    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('UPDATE products')
        ->and($sqlLog[0]['sql'])->toContain('scopes')
        ->and($sqlLog[0]['bindings'])->toContain(null);
});

it('updates only the scopes column when only overrides change', function (): void {
    $sqlLog = [];
    $repository = makeRepository($sqlLog);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $product->attachCompanion($overrides);

    // INSERT
    $repository->save($product);

    // Now mutate only the overrides
    $sqlLog = [];
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('UPDATE products')
        ->and($sqlLog[0]['sql'])->toContain('scopes')
        ->and($sqlLog[0]['sql'])->not->toContain('name =');
});

it('saves an entity with overrides serializing them into the scopes column', function (): void {
    $sqlLog = [];
    $repository = makeRepository($sqlLog);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('INSERT INTO products')
        ->and($sqlLog[0]['sql'])->toContain('scopes')
        ->and($sqlLog[0]['bindings'])->toContain('{"geo:eu.de":{"name":"Hemd"}}');
});

it('saves an entity with no overrides leaving the scopes column null', function (): void {
    $sqlLog = [];
    $repository = makeRepository($sqlLog);

    $product = new Product();
    $product->name = 'Shirt';

    $overrides = new ProductScopedOverrides();
    $product->attachCompanion($overrides);

    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('INSERT INTO products')
        ->and($sqlLog[0]['sql'])->toContain('scopes')
        ->and($sqlLog[0]['bindings'])->toContain(null);
});
