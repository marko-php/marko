<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Tests\Entity\Fixtures\UntypedPropertyEntity;

beforeEach(function (): void {
    $this->factory = new EntityMetadataFactory();
});

it('creates EntityMetadataFactory to parse entity classes via reflection', function (): void {
    expect($this->factory)->toBeInstanceOf(EntityMetadataFactory::class);
});

it('extracts #[Table] attribute for table name', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata)->toBeInstanceOf(EntityMetadata::class);
    expect($metadata->tableName)->toBe('posts');
    expect($metadata->entityClass)->toBe($entity::class);
});

it('extracts #[Column] attributes from all public properties', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 255)]
        public string $title;

        #[Column(type: 'TEXT')]
        public string $content;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns)->toHaveCount(3);

    expect($metadata->columns[0]->name)->toBe('id');
    expect($metadata->columns[0]->primaryKey)->toBeTrue();
    expect($metadata->columns[0]->autoIncrement)->toBeTrue();

    expect($metadata->columns[1]->name)->toBe('title');
    expect($metadata->columns[1]->length)->toBe(255);

    expect($metadata->columns[2]->name)->toBe('content');
    expect($metadata->columns[2]->type)->toBe('TEXT');
});

it('extracts #[Index] attributes from class', function (): void {
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

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->indexes)->toHaveCount(2);

    expect($metadata->indexes[0]->name)->toBe('idx_title');
    expect($metadata->indexes[0]->columns)->toBe(['title']);
    expect($metadata->indexes[0]->unique)->toBeFalse();

    expect($metadata->indexes[1]->name)->toBe('idx_slug_status');
    expect($metadata->indexes[1]->columns)->toBe(['slug', 'status']);
    expect($metadata->indexes[1]->unique)->toBeTrue();
});

it('infers column type from PHP property type (int to INT, string to VARCHAR, etc)', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column]
        public int $intColumn;

        #[Column]
        public string $stringColumn;

        #[Column]
        public float $floatColumn;

        #[Column]
        public bool $boolColumn;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->type)->toBe('INT');
    expect($metadata->columns[1]->type)->toBe('VARCHAR');
    expect($metadata->columns[2]->type)->toBe('DECIMAL');
    expect($metadata->columns[3]->type)->toBe('BOOLEAN');
});

it('infers nullable from nullable PHP type (?string)', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column]
        public int $required;

        #[Column]
        public ?string $optional;

        #[Column]
        public ?int $nullableInt;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->nullable)->toBeFalse();
    expect($metadata->columns[1]->nullable)->toBeTrue();
    expect($metadata->columns[2]->nullable)->toBeTrue();
});

it('infers default from property initializer', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column]
        public int $count = 0;

        #[Column]
        public string $status = 'draft';

        #[Column]
        public bool $active = true;

        #[Column]
        public int $noDefault;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->default)->toBe(0);
    expect($metadata->columns[1]->default)->toBe('draft');
    expect($metadata->columns[2]->default)->toBe(true);
    expect($metadata->columns[3]->default)->toBeNull();
});

it('uses Column attribute name when specified', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(name: 'user_id')]
        public int $userId;

        #[Column]
        public string $title;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->name)->toBe('user_id');
    expect($metadata->columns[1]->name)->toBe('title');
});

it('uses Column attribute type when specified over inferred type', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(type: 'BIGINT')]
        public int $id;

        #[Column(type: 'TEXT')]
        public string $content;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->type)->toBe('BIGINT');
    expect($metadata->columns[1]->type)->toBe('TEXT');
});

it('extracts foreign key reference from Column attribute', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id', onDelete: 'CASCADE', onUpdate: 'SET NULL')]
        public int $userId;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->references)->toBe('users.id');
    expect($metadata->columns[1]->onDelete)->toBe('CASCADE');
    expect($metadata->columns[1]->onUpdate)->toBe('SET NULL');
});

it('caches parsed metadata for performance', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $metadata1 = $this->factory->parse($entity::class);
    $metadata2 = $this->factory->parse($entity::class);

    expect($metadata1)->toBe($metadata2);
});

it('throws EntityException for class without #[Table] attribute', function (): void {
    $entity = new class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->factory->parse($entity::class);
})->throws(EntityException::class, 'missing #[Table] attribute');

it('throws EntityException for class not extending Entity', function (): void {
    $entity = new #[Table('test')] class ()
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $this->factory->parse($entity::class);
})->throws(EntityException::class, 'must extend Entity');

it('throws EntityException for entity without any columns', function (): void {
    $entity = new #[Table('test')] class () extends Entity {};

    $this->factory->parse($entity::class);
})->throws(EntityException::class, 'must have at least one');

it('throws EntityException for auto-increment on non-primary key', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(autoIncrement: true)]
        public int $counter;
    };

    $this->factory->parse($entity::class);
})->throws(EntityException::class, 'autoIncrement');

it('throws EntityException for property without type declaration', function (): void {
    // @phpstan-ignore-next-line
    eval('
        namespace Marko\Database\Tests\Entity\Fixtures;

        use Marko\Database\Attributes\Column;
        use Marko\Database\Attributes\Table;
        use Marko\Database\Entity\Entity;

        #[Table("test")]
        class UntypedPropertyEntity extends Entity
        {
            #[Column]
            public $untyped;
        }
    ');

    $this->factory->parse(UntypedPropertyEntity::class);
})->throws(EntityException::class, 'must have a type declaration');

it('clears cached metadata', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $metadata1 = $this->factory->parse($entity::class);

    $this->factory->clearCache();

    $metadata2 = $this->factory->parse($entity::class);

    expect($metadata1)->not->toBe($metadata2);
    expect($metadata1->tableName)->toBe($metadata2->tableName);
});
