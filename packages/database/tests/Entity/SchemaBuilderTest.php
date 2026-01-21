<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\Index as SchemaIndex;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table as SchemaTable;

beforeEach(function (): void {
    $this->metadataFactory = new EntityMetadataFactory();
    $this->schemaBuilder = new SchemaBuilder();
});

it('creates SchemaBuilder to convert EntityMetadata to Table value object', function (): void {
    expect($this->schemaBuilder)->toBeInstanceOf(SchemaBuilder::class);
});

it('converts EntityMetadata to Table value object', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 255)]
        public string $title;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table)->toBeInstanceOf(SchemaTable::class);
    expect($table->name)->toBe('posts');
    expect($table->columns)->toHaveCount(2);
});

it('converts ColumnMetadata to Schema Column', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 100)]
        public string $name;

        #[Column(unique: true)]
        public string $email;

        #[Column]
        public ?string $bio;

        #[Column(type: 'INT', default: 0)]
        public int $age;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->columns[0])->toBeInstanceOf(SchemaColumn::class);
    expect($table->columns[0]->name)->toBe('id');
    expect($table->columns[0]->type)->toBe('INT');
    expect($table->columns[0]->primaryKey)->toBeTrue();
    expect($table->columns[0]->autoIncrement)->toBeTrue();

    expect($table->columns[1]->name)->toBe('name');
    expect($table->columns[1]->type)->toBe('VARCHAR');
    expect($table->columns[1]->length)->toBe(100);

    expect($table->columns[2]->name)->toBe('email');
    expect($table->columns[2]->unique)->toBeTrue();

    expect($table->columns[3]->name)->toBe('bio');
    expect($table->columns[3]->nullable)->toBeTrue();

    expect($table->columns[4]->name)->toBe('age');
    expect($table->columns[4]->default)->toBe(0);
});

it('converts IndexMetadata to Schema Index', function (): void {
    $entity = new #[Table('posts')]
    #[Index('idx_title', ['title'])]
    #[Index('idx_slug_status', ['slug', 'status'], unique: true)]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $title;

        #[Column]
        public string $slug;

        #[Column]
        public string $status;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->indexes)->toHaveCount(2);

    expect($table->indexes[0])->toBeInstanceOf(SchemaIndex::class);
    expect($table->indexes[0]->name)->toBe('idx_title');
    expect($table->indexes[0]->columns)->toBe(['title']);
    expect($table->indexes[0]->type)->toBe(IndexType::Btree);

    expect($table->indexes[1]->name)->toBe('idx_slug_status');
    expect($table->indexes[1]->columns)->toBe(['slug', 'status']);
    expect($table->indexes[1]->type)->toBe(IndexType::Unique);
});

it('preserves foreign key references in Schema Column', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id', onDelete: 'CASCADE', onUpdate: 'SET NULL')]
        public int $userId;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->columns[1]->references)->toBe('users.id');
    expect($table->columns[1]->onDelete)->toBe('CASCADE');
    expect($table->columns[1]->onUpdate)->toBe('SET NULL');
});
