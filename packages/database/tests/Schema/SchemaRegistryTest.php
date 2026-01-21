<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table as SchemaTable;

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
