<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Scope\Storage\ScopedOverridesEntity;

// ── Fixtures ─────────────────────────────────────────────────────────────────

#[Table('products')]
class AutoMigrationProduct extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 255)]
    public string $name;
}

#[Table(extends: AutoMigrationProduct::class)]
class AutoMigrationProductScopedOverrides extends ScopedOverridesEntity {}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('registers a Product entity and a ProductScopedOverrides extender in the SchemaRegistry', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    expect($registry->hasTable('products'))->toBeTrue()
        ->and($registry->getTables())->toHaveCount(1)
        ->and($registry->getEntityClass('products'))->toBe(AutoMigrationProduct::class);
});

it('merges the scopes column into the parent products table at schema-build time', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    $table = $registry->getTable('products');
    $columnNames = array_map(fn ($c) => $c->name, $table->columns);

    expect($columnNames)->toContain('scopes')
        ->and($table->columns)->toHaveCount(3);
});

it('emits ALTER TABLE products ADD COLUMN scopes JSON NULL when diffing against an empty schema', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    $existingProductsTable = new SchemaTable(
        name: 'products',
        columns: [
            new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new SchemaColumn(name: 'name', type: 'varchar', length: 255),
        ],
        indexes: [],
    );

    $diff = (new DiffCalculator())->calculate(
        $registry->getTables(),
        ['products' => $existingProductsTable],
    );

    $statements = (new MySqlGenerator())->generateUp($diff);

    expect($statements)->toHaveCount(1)
        ->and($statements[0])->toContain('ALTER TABLE `products`')
        ->and($statements[0])->toContain('ADD COLUMN')
        ->and($statements[0])->toContain('`scopes`')
        ->and($statements[0])->toContain('JSON')
        ->and($statements[0])->toContain('NULL');
});

it('does not emit a separate scopes_overrides table or treat the extender as a standalone table', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    $diff = (new DiffCalculator())->calculate(
        $registry->getTables(),
        [],
    );

    $statements = (new MySqlGenerator())->generateUp($diff);
    $tableNames = $registry->getTableNames();

    expect($tableNames)->toHaveCount(1)
        ->and($tableNames)->toContain('products')
        ->and($tableNames)->not->toContain('auto_migration_product_scoped_overrides')
        ->and($statements)->toHaveCount(1)
        ->and($statements[0])->toContain('CREATE TABLE `products`');
    foreach ($statements as $statement) {
        expect($statement)->not->toContain('scopes_overrides');
    }
});

it('preserves the parent entity columns in the merged schema', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    $table = $registry->getTable('products');
    $columnNames = array_map(fn ($c) => $c->name, $table->columns);

    expect($columnNames)->toContain('id')
        ->and($columnNames)->toContain('name')
        ->and($columnNames)->toContain('scopes');

    $idColumn = array_values(array_filter($table->columns, fn ($c) => $c->name === 'id'))[0];
    expect($idColumn->primaryKey)->toBeTrue()
        ->and($idColumn->autoIncrement)->toBeTrue();
});

it('does not emit any ALTER TABLE statement when the scopes column already exists', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([
        AutoMigrationProduct::class,
        AutoMigrationProductScopedOverrides::class,
    ]);

    $existingProductsTable = new SchemaTable(
        name: 'products',
        columns: [
            new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new SchemaColumn(name: 'name', type: 'varchar', length: 255),
            new SchemaColumn(name: 'scopes', type: 'json', nullable: true),
        ],
        indexes: [],
    );

    $diff = (new DiffCalculator())->calculate(
        $registry->getTables(),
        ['products' => $existingProductsTable],
    );

    $statements = (new MySqlGenerator())->generateUp($diff);

    expect($statements)->toBeEmpty();
});
