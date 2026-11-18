<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\ForeignKeyMetadata;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\MissingPrimaryKeyException;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\BasicExtenderEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ChainedExtenderEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderParentEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithAutoIncrementEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithIndexEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithMissingParentEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithNonEntityParentEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithOwnNameEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithPrimaryKeyEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\ExtenderWithRelationshipEntity;
use Marko\Database\Tests\Entity\Fixtures\ExtenderFactory\TableWithNeitherNameNorExtendsEntity;

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
    #[Index(['title'])]
    #[Index(['slug', 'status'], unique: true)]
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
        ->and($metadata->indexes[0]->name)->toBeNull()
        ->and($metadata->indexes[0]->columns)->toBe(['title'])
        ->and($metadata->indexes[0]->unique)->toBeFalse()
        ->and($metadata->indexes[1]->name)->toBeNull()
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

it('parses a union-typed property when an explicit column type is declared', function (): void {
    $entity = new #[Table('attachments')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public ?int $id = null;

        #[Column(type: 'varchar', length: 255)]
        public int|string $attachableId = 0;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->properties['attachableId']->type)->toContain('int')
        ->and($metadata->properties['attachableId']->type)->toContain('string')
        ->and($metadata->properties['attachableId']->columnType)->toBe('varchar')
        ->and($metadata->columns[1]->type)->toBe('varchar')
        ->and($metadata->columns[1]->nullable)->toBeFalse();
});

it('throws EntityException for a union-typed property without an explicit column type', function (): void {
    $entity = new #[Table('attachments')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public ?int $id = null;

        #[Column(length: 255)]
        public int|string $attachableId = 0;
    };

    $this->factory->parse($entity::class);
})->throws(EntityException::class, 'must declare an explicit column type');

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

it(
    'throws MissingPrimaryKeyException at metadata parse time when entity has no primary key attribute',
    function (): void {
        $entity = new #[Table('no_pk')] class () extends Entity
        {
            #[Column]
            public int $userId;

            #[Column]
            public string $name;
        };

        $this->factory->parse($entity::class);
    },
)->throws(MissingPrimaryKeyException::class);

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

it('parses an extender entity and resolves table name from the parent', function (): void {
    $metadata = $this->factory->parse(BasicExtenderEntity::class);

    expect($metadata->tableName)->toBe('users');
});

it('populates extends field on extender metadata with the parent class-string', function (): void {
    $metadata = $this->factory->parse(BasicExtenderEntity::class);

    expect($metadata->extends)->toBe(ExtenderParentEntity::class);
});

it('throws EntityException when extender declares its own name on Table attribute', function (): void {
    $this->factory->parse(ExtenderWithOwnNameEntity::class);
})->throws(EntityException::class, 'declares its own name');

it('throws EntityException when entity declares neither name nor extends on Table attribute', function (): void {
    $this->factory->parse(TableWithNeitherNameNorExtendsEntity::class);
})->throws(EntityException::class, 'requires either name: or extends:');

it('throws EntityException when extender declares a primaryKey column', function (): void {
    $this->factory->parse(ExtenderWithPrimaryKeyEntity::class);
})->throws(EntityException::class, 'declares a primaryKey column');

it('throws EntityException when extender declares an autoIncrement column', function (): void {
    $this->factory->parse(ExtenderWithAutoIncrementEntity::class);
})->throws(EntityException::class, 'declares an autoIncrement column');

it("throws EntityException when extender's parent class does not exist", function (): void {
    $this->factory->parse(ExtenderWithMissingParentEntity::class);
})->throws(EntityException::class, 'does not exist');

it("throws EntityException when extender's parent class does not extend Entity", function (): void {
    $this->factory->parse(ExtenderWithNonEntityParentEntity::class);
})->throws(EntityException::class, 'does not extend Entity');

it("throws EntityException when extender's parent is itself an extender (no chained extension)", function (): void {
    $this->factory->parse(ChainedExtenderEntity::class);
})->throws(EntityException::class, 'Chained extension is not supported');

it('allows extender to declare its own indexes', function (): void {
    $metadata = $this->factory->parse(ExtenderWithIndexEntity::class);

    expect($metadata->indexes)->toHaveCount(1)
        ->and($metadata->indexes[0]->name)->toBeNull();
});

it('allows extender to declare relationships', function (): void {
    $metadata = $this->factory->parse(ExtenderWithRelationshipEntity::class);

    expect($metadata->relationships)->toHaveKey('related');
});

it('caches extender metadata like normal entities', function (): void {
    $metadata1 = $this->factory->parse(BasicExtenderEntity::class);
    $metadata2 = $this->factory->parse(BasicExtenderEntity::class);

    expect($metadata1)->toBe($metadata2);
});

it('linkExtenders replaces the cached parent metadata with one that has extenders populated', function (): void {
    $this->factory->parse(ExtenderParentEntity::class);
    $this->factory->linkExtenders(ExtenderParentEntity::class, [BasicExtenderEntity::class]);
    $updated = $this->factory->parse(ExtenderParentEntity::class);

    expect($updated->extenders)->toBe([BasicExtenderEntity::class]);
});

it('linkExtenders returns metadata where isExtended is true', function (): void {
    $this->factory->parse(ExtenderParentEntity::class);
    $result = $this->factory->linkExtenders(ExtenderParentEntity::class, [BasicExtenderEntity::class]);

    expect($result->isExtended())->toBeTrue();
});

it(
    'produces a chained-extension error message that names both the extender and its extender-parent and tells the user to extend the root',
    function (): void {
        $extender = ChainedExtenderEntity::class;
        $parent = BasicExtenderEntity::class;

        expect(fn () => $this->factory->parse($extender))
            ->toThrow(
                EntityException::class,
                "Chained extension is not supported. $extender's parent $parent is itself an extender. Extend the root entity directly.",
            );
    },
);

it('linkExtendersFrom scans entity classes and links extenders to their parents', function (): void {
    $this->factory->linkExtendersFrom([
        ExtenderParentEntity::class,
        BasicExtenderEntity::class,
    ]);

    $parentMetadata = $this->factory->parse(ExtenderParentEntity::class);

    expect($parentMetadata->extenders)->toBe([BasicExtenderEntity::class]);
});

it('linkExtendersFrom ignores entities without extends', function (): void {
    $this->factory->linkExtendersFrom([ExtenderParentEntity::class]);

    $parentMetadata = $this->factory->parse(ExtenderParentEntity::class);

    expect($parentMetadata->extenders)->toBeEmpty();
});

it('linkExtendersFrom throws when an extender references a parent class that cannot be autoloaded', function (): void {
    expect(fn () => $this->factory->linkExtendersFrom([ExtenderWithMissingParentEntity::class]))
        ->toThrow(EntityException::class, 'does not exist');
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

describe('generated primary keys', function (): void {
    it('marks a generated primary key as generated in the property metadata', function (): void {
        $entity = new #[Table('generated_keys')] class () extends Entity
        {
            #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]
            public string $id;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->properties['id']->isGenerated)->toBeTrue();
    });

    it('leaves isGenerated false when the column does not declare generated', function (): void {
        $entity = new #[Table('plain_keys')] class () extends Entity
        {
            #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()')]
            public string $id;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->properties['id']->isGenerated)->toBeFalse();
    });

    it('throws when generated is declared on a column that is not the primary key', function (): void {
        $entity = new #[Table('generated_non_key')] class () extends Entity
        {
            #[Column(primaryKey: true)]
            public string $id;

            #[Column(type: 'uuid', default: 'gen_random_uuid()', generated: true)]
            public string $token;
        };

        expect(fn () => $this->factory->parse($entity::class))
            ->toThrow(EntityException::class, "Property 'token'");
    });

    it('throws when generated is combined with autoIncrement', function (): void {
        $entity = new #[Table('generated_auto_increment')] class () extends Entity
        {
            #[Column(primaryKey: true, autoIncrement: true, default: 'nextval()', generated: true)]
            public int $id;
        };

        expect(fn () => $this->factory->parse($entity::class))
            ->toThrow(EntityException::class, 'both generated and autoIncrement');
    });

    it('throws when generated is declared without a default', function (): void {
        $entity = new #[Table('generated_without_default')] class () extends Entity
        {
            #[Column(primaryKey: true, type: 'uuid', generated: true)]
            public string $id;
        };

        expect(fn () => $this->factory->parse($entity::class))
            ->toThrow(EntityException::class, 'has no default');
    });
});

describe('foreign keys', function (): void {
    it('parses a ForeignKey attribute into foreign key metadata', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(['user_id'], 'users', ['id'], 'CASCADE', 'RESTRICT', 'fk_posts_author')]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->foreignKeys)
            ->toHaveCount(1)
            ->and($metadata->foreignKeys[0])->toBeInstanceOf(ForeignKeyMetadata::class)
            ->and($metadata->foreignKeys[0]->columns)->toBe(['user_id'])
            ->and($metadata->foreignKeys[0]->referencedTable)->toBe('users')
            ->and($metadata->foreignKeys[0]->referencedColumns)->toBe(['id'])
            ->and($metadata->foreignKeys[0]->onDelete)->toBe('CASCADE')
            ->and($metadata->foreignKeys[0]->onUpdate)->toBe('RESTRICT')
            ->and($metadata->foreignKeys[0]->name)->toBe('fk_posts_author');
    });

    it('parses multiple ForeignKey attributes on one entity in declaration order', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(columns: ['user_id'], references: 'users', referencedColumns: ['id'])]
        #[ForeignKey(columns: ['category_id'], references: 'categories', referencedColumns: ['id'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;

            #[Column]
            public int $categoryId;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->foreignKeys)
            ->toHaveCount(2)
            ->and($metadata->foreignKeys[0]->referencedTable)->toBe('users')
            ->and($metadata->foreignKeys[0]->name)->toBeNull()
            ->and($metadata->foreignKeys[1]->referencedTable)->toBe('categories');
    });

    it('parses a composite ForeignKey keeping column order', function (): void {
        $entity = new #[Table('order_lines')]
        #[ForeignKey(columns: ['tenant_id', 'order_id'], references: 'orders', referencedColumns: ['tenant_id', 'id'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $orderId;

            #[Column]
            public int $tenantId;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->foreignKeys[0]->columns)->toBe(['tenant_id', 'order_id'])
            ->and($metadata->foreignKeys[0]->referencedColumns)->toBe(['tenant_id', 'id']);
    });

    it('throws when a ForeignKey declares no columns', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(columns: [], references: 'users', referencedColumns: [])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;
        };

        $parse = fn () => $this->factory->parse($entity::class);

        expect($parse)->toThrow(EntityException::class, 'declares no columns');
    });

    it('throws when a ForeignKey column count differs from its referenced column count', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(columns: ['user_id', 'tenant_id'], references: 'users', referencedColumns: ['id'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;

            #[Column]
            public int $tenantId;
        };

        $parse = fn () => $this->factory->parse($entity::class);

        expect($parse)->toThrow(EntityException::class, '2 column(s)');
    });

    it('throws when a ForeignKey names a column the entity does not have', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(columns: ['author_id'], references: 'users', referencedColumns: ['id'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;
        };

        $parse = fn () => $this->factory->parse($entity::class);

        expect($parse)->toThrow(EntityException::class, "'author_id'");
    });

    it('suggests the column name when a ForeignKey names a property instead', function (): void {
        $entity = new #[Table('posts')]
        #[ForeignKey(columns: ['userId'], references: 'users', referencedColumns: ['id'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;
        };

        try {
            $this->factory->parse($entity::class);
            $exception = null;
        } catch (EntityException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(EntityException::class)
            ->and($exception->getMessage())->toContain("'userId'")
            ->and($exception->getSuggestion())->toContain("use the column name 'user_id'");
    });

    it('throws when an Index names a column the entity does not have', function (): void {
        $entity = new #[Table('posts')]
        #[Index(['slug'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public string $title;
        };

        $parse = fn () => $this->factory->parse($entity::class);

        expect($parse)->toThrow(EntityException::class, "'slug'");
    });

    it('suggests the column name when an Index names a property instead', function (): void {
        $entity = new #[Table('posts')]
        #[Index(['userId'])]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;
        };

        try {
            $this->factory->parse($entity::class);
            $exception = null;
        } catch (EntityException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(EntityException::class)
            ->and($exception->getMessage())->toContain("'userId'")
            ->and($exception->getSuggestion())->toContain("use the column name 'user_id'");
    });

    it('accepts an Index on existing columns', function (): void {
        $entity = new #[Table('posts')]
        #[Index(['user_id', 'title'], unique: true)]
        class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column]
            public int $userId;

            #[Column]
            public string $title;
        };

        $metadata = $this->factory->parse($entity::class);

        expect($metadata->indexes)->toHaveCount(1)
            ->and($metadata->indexes[0]->columns)->toBe(['user_id', 'title']);
    });

    it('keeps foreign key metadata when extenders are linked', function (): void {
        $foreignKey = new ForeignKeyMetadata(
            columns: ['user_id'],
            referencedTable: 'users',
            referencedColumns: ['id'],
        );
        $original = new EntityMetadata(
            entityClass: 'App\Entity\Post',
            tableName: 'posts',
            primaryKey: 'id',
            foreignKeys: [$foreignKey],
        );

        $linked = $original->withExtenders(['App\Entity\ExtPost']);

        expect($linked->foreignKeys)->toBe([$foreignKey]);
    });
});
