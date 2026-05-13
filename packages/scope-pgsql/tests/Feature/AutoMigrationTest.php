<?php

declare(strict_types=1);

namespace Marko\Scope\PgSql\Tests\Feature;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table as SchemaTable;
use Marko\Scope\Storage\ScopedOverridesEntity;

// Test fixtures

#[Table('products')]
class Product extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 255)]
    public string $name;
}

#[Table(extends: Product::class)]
class ProductScopedOverrides extends ScopedOverridesEntity {}

// Tests

it('registers a Product entity and a ProductScopedOverrides extender in the SchemaRegistry', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    expect($registry->hasTable('products'))->toBeTrue()
        ->and($registry->getEntityClass('products'))->toBe(Product::class);
});

it('merges the scopes column into the parent products table at schema-build time', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    $table = $registry->getTable('products');
    $columnNames = array_map(fn ($col) => $col->name, $table->columns);

    expect($columnNames)->toContain('scopes');
});

it('does not emit a separate scopes_overrides table', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    expect($registry->getTables())->toHaveCount(1)
        ->and($registry->hasTable('products'))->toBeTrue()
        ->and($registry->hasTable('scopes_overrides'))->toBeFalse();
});

it('preserves the parent entity columns in the merged schema', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    $table = $registry->getTable('products');
    $columnNames = array_map(fn ($col) => $col->name, $table->columns);

    expect($columnNames)->toContain('id')
        ->and($columnNames)->toContain('name')
        ->and($columnNames)->toContain('scopes');
});

it('aliases json to JSONB via PgSqlGenerator TYPE_MAP rather than emitting JSON', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    $databaseSchema = [
        'products' => new SchemaTable(
            name: 'products',
            columns: [
                new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new SchemaColumn(name: 'name', type: 'varchar', length: 255),
            ],
            indexes: [],
        ),
    ];

    $diff = (new DiffCalculator())->calculate($registry->getTables(), $databaseSchema);
    $generator = new PgSqlGenerator();
    $statements = $generator->generateUp($diff);

    expect($statements[0])->toContain('JSONB')
        ->and($statements[0])->not->toContain('JSON ');
});

it('does not emit any ALTER TABLE statement when the scopes column already exists', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    // Database state: products table already has the scopes column
    $databaseSchema = [
        'products' => new SchemaTable(
            name: 'products',
            columns: [
                new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new SchemaColumn(name: 'name', type: 'varchar', length: 255),
                new SchemaColumn(name: 'scopes', type: 'json', nullable: true),
            ],
            indexes: [],
        ),
    ];

    $diff = (new DiffCalculator())->calculate($registry->getTables(), $databaseSchema);
    $generator = new PgSqlGenerator();
    $statements = $generator->generateUp($diff);

    expect($statements)->toBeEmpty();
});

it('emits ALTER TABLE products ADD COLUMN scopes JSONB when diffing against an empty schema', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );

    $registry->registerEntities([Product::class, ProductScopedOverrides::class]);

    // Database state: products table exists but without the scopes column
    $databaseSchema = [
        'products' => new SchemaTable(
            name: 'products',
            columns: [
                new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
                new SchemaColumn(name: 'name', type: 'varchar', length: 255),
            ],
            indexes: [],
        ),
    ];

    $diff = (new DiffCalculator())->calculate($registry->getTables(), $databaseSchema);
    $generator = new PgSqlGenerator();
    $statements = $generator->generateUp($diff);

    expect($statements)->toHaveCount(1)
        ->and($statements[0])->toContain('ALTER TABLE "products"')
        ->and($statements[0])->toContain('ADD COLUMN')
        ->and($statements[0])->toContain('"scopes"')
        ->and($statements[0])->toContain('JSONB');
});
