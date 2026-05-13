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

// Parent entity for dirty tracking feature test
#[Table('products')]
class DirtyTrackingProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $name;
}

// HasScopesInterface companion for DirtyTrackingProduct
#[Table(extends: DirtyTrackingProduct::class)]
class DirtyTrackingProductOverrides extends Entity implements HasScopesInterface
{
    use HasScopes;
}

// Repository for DirtyTrackingProduct
class DirtyTrackingProductRepository extends Repository
{
    protected const string ENTITY_CLASS = DirtyTrackingProduct::class;
}

it('participates in Repository::save dirty tracking via the existing companion path', function (): void {
    $sqlLog = [];

    $connection = new class ($sqlLog) implements ConnectionInterface
    {
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

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };

    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($metadataFactory);
    $repository = new DirtyTrackingProductRepository($connection, $metadataFactory, $hydrator);

    $product = new DirtyTrackingProduct();
    $product->name = 'Shirt';

    $overrides = new DirtyTrackingProductOverrides();
    $overrides->setOverride('geo:eu.de', 'name', 'Hemd');
    $product->attachCompanion($overrides);

    // INSERT — includes scopes column
    $repository->save($product);

    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('INSERT INTO products')
        ->and($sqlLog[0]['sql'])->toContain('scopes');

    // After INSERT, overrides has originalValues registered — mutate and re-save
    $sqlLog = [];
    $overrides->setOverride('locale:de', 'name', 'Hallo');
    $repository->save($product);

    // UPDATE should include the scopes column (it is dirty)
    expect($sqlLog)->toHaveCount(1)
        ->and($sqlLog[0]['sql'])->toContain('UPDATE products')
        ->and($sqlLog[0]['sql'])->toContain('scopes');

    // No further mutation — re-save must NOT issue SQL
    $sqlLog = [];
    $repository->save($product);

    expect($sqlLog)->toBeEmpty();
});
