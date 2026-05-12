<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Database\Tests\Schema\Fixtures\InvoiceEntity;
use Marko\Database\Tests\Schema\Fixtures\InvoiceExtenderOneIndexEntity;
use Marko\Database\Tests\Schema\Fixtures\InvoiceExtenderTwoConflictIndexEntity;
use Marko\Database\Tests\Schema\Fixtures\OrderEntity;
use Marko\Database\Tests\Schema\Fixtures\OrderExtenderConflictsWithParentEntity;
use Marko\Database\Tests\Schema\Fixtures\OrderExtenderOneEntity;
use Marko\Database\Tests\Schema\Fixtures\OrderExtenderTwoConflictEntity;
use Marko\Database\Tests\Schema\Fixtures\OrphanExtenderEntity;
use Marko\Database\Tests\Schema\Fixtures\ProductEntity;
use Marko\Database\Tests\Schema\Fixtures\ProductExtenderEntity;
use Marko\Database\Tests\Schema\Fixtures\ProductFkExtenderEntity;
use Marko\Database\Tests\Schema\Fixtures\ProductIndexExtenderEntity;
use Marko\Database\Tests\Schema\Fixtures\ProductSecondExtenderEntity;

beforeEach(function (): void {
    $this->metadataFactory = new EntityMetadataFactory();
    $this->schemaBuilder = new SchemaBuilder();
    $this->registry = new SchemaRegistry(
        metadataFactory: $this->metadataFactory,
        schemaBuilder: $this->schemaBuilder,
    );
});

it('populates SchemaRegistry with all discovered tables', function (): void {
    expect($this->registry)->toBeInstanceOf(SchemaRegistry::class);
});

it('registers table schema from entity class', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 255)]
        public string $title;
    };

    $this->registry->registerEntity($entity::class);

    expect($this->registry->hasTable('posts'))->toBeTrue()
        ->and($this->registry->getTable('posts'))->toBeInstanceOf(SchemaTable::class)
        ->and($this->registry->getTable('posts')->columns)->toHaveCount(2);
});

it('retrieves all registered tables', function (): void {
    $entity1 = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $entity2 = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->registry->registerEntity($entity1::class);
    $this->registry->registerEntity($entity2::class);

    $tables = $this->registry->getTables();

    expect($tables)->toHaveCount(2)
        ->and(array_keys($tables))->toContain('users', 'posts');
});

it('retrieves entity class by table name', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->registry->registerEntity($entity::class);

    expect($this->registry->getEntityClass('posts'))->toBe($entity::class);
});

it('retrieves EntityMetadata by table name', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $title;
    };

    $this->registry->registerEntity($entity::class);

    $metadata = $this->registry->getMetadata('posts');

    expect($metadata)->not->toBeNull()
        ->and($metadata->tableName)->toBe('posts')
        ->and($metadata->columns)->toHaveCount(2);
});

it('returns null for unknown table', function (): void {
    expect($this->registry->getTable('nonexistent'))->toBeNull()
        ->and($this->registry->getEntityClass('nonexistent'))->toBeNull()
        ->and($this->registry->getMetadata('nonexistent'))->toBeNull();
});

it('registers multiple entities at once', function (): void {
    $entity1 = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $entity2 = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->registry->registerEntities([
        $entity1::class,
        $entity2::class,
    ]);

    expect($this->registry->hasTable('users'))->toBeTrue()
        ->and($this->registry->hasTable('posts'))->toBeTrue();
});

it('gets all table names', function (): void {
    $entity1 = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $entity2 = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->registry->registerEntities([
        $entity1::class,
        $entity2::class,
    ]);

    $names = $this->registry->getTableNames();

    expect($names)->toContain('users', 'posts')
        ->and($names)->toHaveCount(2);
});

it('clears all registered tables', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->registry->registerEntity($entity::class);
    expect($this->registry->hasTable('posts'))->toBeTrue();

    $this->registry->clear();

    expect($this->registry->hasTable('posts'))->toBeFalse()
        ->and($this->registry->getTables())->toBe([]);
});

// ─── Two-pass merge requirements ───────────────────────────────────────────

it('registers a parent entity alone with no extenders', function (): void {
    $this->registry->registerEntities([ProductEntity::class]);

    expect($this->registry->hasTable('products'))->toBeTrue()
        ->and($this->registry->getTable('products')->columns)->toHaveCount(2)
        ->and($this->registry->getMetadata('products')->extenders)->toBeEmpty();
});

it('registers a parent entity with one extender and merges columns into the parent table', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    $table = $this->registry->getTable('products');

    expect($table)->not->toBeNull()
        ->and($table->columns)->toHaveCount(3)
        ->and(array_map(fn ($c) => $c->name, $table->columns))->toContain('sku');
});

it('registers a parent entity with multiple extenders and merges columns from all', function (): void {
    $this->registry->registerEntities([
        ProductEntity::class,
        ProductExtenderEntity::class,
        ProductSecondExtenderEntity::class,
    ]);

    $table = $this->registry->getTable('products');
    $columnNames = array_map(fn ($c) => $c->name, $table->columns);

    expect($table->columns)->toHaveCount(4)
        ->and($columnNames)->toContain('sku')
        ->and($columnNames)->toContain('barcode');
});

it('merges extender indexes into the parent table', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductIndexExtenderEntity::class]);

    $table = $this->registry->getTable('products');

    expect($table->indexes)->toHaveCount(1)
        ->and($table->indexes[0]->name)->toBe('idx_products_sku');
});

it('preserves the parent primary key in the merged table', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    $table = $this->registry->getTable('products');
    $primaryKey = array_find($table->columns, fn ($c) => $c->primaryKey);

    expect($primaryKey)->not->toBeNull()
        ->and($primaryKey->name)->toBe('id');
});

it('links extender class-strings into the parent EntityMetadata extenders field', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    $metadata = $this->registry->getMetadata('products');

    expect($metadata->extenders)->toContain(ProductExtenderEntity::class)
        ->and($metadata->isExtended())->toBeTrue();
});

it('throws EntityException when two extenders add a column with the same name', function (): void {
    expect(fn () => $this->registry->registerEntities([
        OrderEntity::class,
        OrderExtenderOneEntity::class,
        OrderExtenderTwoConflictEntity::class,
    ]))->toThrow(EntityException::class);
});

it('throws EntityException when an extender adds a column with the same name as a parent column', function (): void {
    expect(fn () => $this->registry->registerEntities([
        OrderEntity::class,
        OrderExtenderConflictsWithParentEntity::class,
    ]))->toThrow(EntityException::class);
});

it('throws EntityException when registering an extender whose parent class was not registered', function (): void {
    expect(fn () => $this->registry->registerEntities([OrphanExtenderEntity::class]))
        ->toThrow(EntityException::class);
});

it('handles registration order independence (extender registered before parent)', function (): void {
    $this->registry->registerEntities([ProductExtenderEntity::class, ProductEntity::class]);

    $table = $this->registry->getTable('products');

    expect($table)->not->toBeNull()
        ->and($table->columns)->toHaveCount(3)
        ->and(array_map(fn ($c) => $c->name, $table->columns))->toContain('sku');
});

it('does not register the extender as its own Table (no duplicate tables in registry)', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    expect($this->registry->getTables())->toHaveCount(1)
        ->and($this->registry->hasTable('products'))->toBeTrue();
});

it('merges extender foreign keys into the parent table', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductFkExtenderEntity::class]);

    $table = $this->registry->getTable('products');
    $fkNames = array_map(fn ($fk) => $fk->name, $table->foreignKeys);

    expect($table->foreignKeys)->toHaveCount(1)
        ->and($fkNames)->toContain('fk_products_category_id');
});

it('throws EntityException when two extenders declare an index with the same name', function (): void {
    expect(fn () => $this->registry->registerEntities([
        InvoiceEntity::class,
        InvoiceExtenderOneIndexEntity::class,
        InvoiceExtenderTwoConflictIndexEntity::class,
    ]))->toThrow(EntityException::class);
});

it('updates the EntityMetadataFactory cache so that a subsequent parse(parentClass) returns metadata with extenders populated', function (): void {
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    $cachedMetadata = $this->metadataFactory->parse(ProductEntity::class);

    expect($cachedMetadata->extenders)->toContain(ProductExtenderEntity::class);
});

it('handles registration order independence when an extender appears before its parent in the input array', function (): void {
    // Extender listed first, parent second — must still merge correctly
    $this->registry->registerEntities([ProductSecondExtenderEntity::class, ProductEntity::class]);

    $table = $this->registry->getTable('products');
    $columnNames = array_map(fn ($c) => $c->name, $table->columns);

    expect($table)->not->toBeNull()
        ->and($columnNames)->toContain('barcode');
});

it('includes a discovered extender from EntityDiscovery in the merged table (regression test for discovery integration)', function (): void {
    // Both ProductEntity and ProductExtenderEntity extend Entity with #[Table],
    // so EntityDiscovery would find both. Simulate by passing both to registerEntities.
    $this->registry->registerEntities([ProductEntity::class, ProductExtenderEntity::class]);

    $table = $this->registry->getTable('products');

    expect($table)->not->toBeNull()
        ->and($table->columns)->toHaveCount(3)
        ->and($this->registry->getEntityClass('products'))->toBe(ProductEntity::class);
});
