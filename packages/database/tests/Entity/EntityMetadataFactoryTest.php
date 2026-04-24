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
use Marko\Database\Exceptions\MissingPrimaryKeyException;

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

    expect($metadata)
        ->toBeInstanceOf(EntityMetadata::class)
        ->and($metadata->tableName)->toBe('posts')
        ->and($metadata->entityClass)->toBe($entity::class);
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

    expect($metadata->columns)
        ->toHaveCount(3)
        ->and($metadata->columns[0]->name)->toBe('id')
        ->and($metadata->columns[0]->primaryKey)->toBeTrue()
        ->and($metadata->columns[0]->autoIncrement)->toBeTrue()
        ->and($metadata->columns[1]->name)->toBe('title')
        ->and($metadata->columns[1]->length)->toBe(255)
        ->and($metadata->columns[2]->name)->toBe('content')
        ->and($metadata->columns[2]->type)->toBe('TEXT');
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

    expect($metadata->indexes)
        ->toHaveCount(2)
        ->and($metadata->indexes[0]->name)->toBe('idx_title')
        ->and($metadata->indexes[0]->columns)->toBe(['title'])
        ->and($metadata->indexes[0]->unique)->toBeFalse()
        ->and($metadata->indexes[1]->name)->toBe('idx_slug_status')
        ->and($metadata->indexes[1]->columns)->toBe(['slug', 'status'])
        ->and($metadata->indexes[1]->unique)->toBeTrue();
});

it('infers column type from PHP property type (int to integer, string to varchar, etc)', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public int $intColumn;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public string $stringColumn;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public float $floatColumn;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public bool $boolColumn;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->type)
        ->toBe('integer')
        ->and($metadata->columns[2]->type)->toBe('varchar')
        ->and($metadata->columns[3]->type)->toBe('decimal')
        ->and($metadata->columns[4]->type)->toBe('boolean');
});

it('infers nullable from nullable PHP type (?string)', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $required;

        #[Column]
        public ?string $optional;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public ?int $nullableInt;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->nullable)
        ->toBeFalse()
        ->and($metadata->columns[1]->nullable)->toBeTrue()
        ->and($metadata->columns[2]->nullable)->toBeTrue();
});

it('infers default from property initializer', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $count = 0;

        #[Column]
        public string $status = 'draft';

        #[Column]
        public bool $active = true;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public int $noDefault;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->default)
        ->toBe(0)
        ->and($metadata->columns[1]->default)->toBe('draft')
        ->and($metadata->columns[2]->default)->toBeTrue()
        ->and($metadata->columns[3]->default)->toBeNull();
});

it('uses Column attribute name when specified', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true, name: 'author')]
        public int $userId;

        #[Column]
        public string $title;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->name)
        ->toBe('author')
        ->and($metadata->columns[1]->name)->toBe('title');
});

it('uses Column attribute type when specified over inferred type', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true, type: 'BIGINT')]
        public int $id;

        #[Column(type: 'TEXT')]
        public string $content;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->type)
        ->toBe('BIGINT')
        ->and($metadata->columns[1]->type)->toBe('TEXT');
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

    expect($metadata->columns[1]->references)
        ->toBe('users.id')
        ->and($metadata->columns[1]->onDelete)->toBe('CASCADE')
        ->and($metadata->columns[1]->onUpdate)->toBe('SET NULL');
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
    $className = 'Marko\Database\Tests\Entity\Fixtures\UntypedPropertyEntity';

    if (!class_exists($className)) {
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
    }

    $this->factory->parse($className);
})->throws(EntityException::class, 'must have a type declaration');

it('handles leading uppercase sequences correctly (HTMLParser becomes html_parser)', function (): void {
    $entity = new #[Table('records')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public string $HTMLParser;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->name)->toBe('html_parser');
});

it('handles consecutive uppercase letters correctly (userID becomes user_id)', function (): void {
    $entity = new #[Table('records')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        /** @noinspection PhpUnused - Accessed via reflection metadata */
        #[Column]
        public int $userID;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->name)->toBe('user_id');
});

it('handles single-word property names without change', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $name;

        #[Column]
        public string $email;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[0]->name)
        ->toBe('id')
        ->and($metadata->columns[1]->name)->toBe('name')
        ->and($metadata->columns[2]->name)->toBe('email');
});

it('preserves explicit Column name override when specified', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(name: 'author')]
        public int $userId;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->name)->toBe('author');
});

it('converts camelCase property names to snake_case column names automatically', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public int $postId;

        #[Column]
        public string $createdAt;

        #[Column]
        public bool $isActive;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns[1]->name)
        ->toBe('post_id')
        ->and($metadata->columns[2]->name)->toBe('created_at')
        ->and($metadata->columns[3]->name)->toBe('is_active');
});

it('throws MissingPrimaryKeyException at metadata parse time when entity has no primary key attribute', function (): void {
    $entity = new #[Table('no_pk')] class () extends Entity
    {
        #[Column]
        public int $userId;

        #[Column]
        public string $name;
    };

    $this->factory->parse($entity::class);
})->throws(MissingPrimaryKeyException::class);

it('includes the entity class name in the exception message', function (): void {
    $entity = new #[Table('no_pk')] class () extends Entity
    {
        #[Column]
        public int $userId;
    };

    $entityClass = $entity::class;

    expect(fn () => $this->factory->parse($entityClass))
        ->toThrow(MissingPrimaryKeyException::class, $entityClass);
});

it('includes a suggestion to add #[Column(primaryKey: true)] in the exception message', function (): void {
    $entity = new #[Table('no_pk')] class () extends Entity
    {
        #[Column]
        public int $userId;
    };

    $exception = null;

    try {
        $this->factory->parse($entity::class);
    } catch (MissingPrimaryKeyException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getSuggestion())->toContain('#[Column(primaryKey: true)]');
});

it('clears cached metadata', function (): void {
    $entity = new #[Table('test')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $metadata1 = $this->factory->parse($entity::class);

    $this->factory->clearCache();

    $metadata2 = $this->factory->parse($entity::class);

    expect($metadata1)
        ->not->toBe($metadata2)
        ->and($metadata1->tableName)->toBe($metadata2->tableName);
});
