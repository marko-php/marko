<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\ColumnMetadata;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index as SchemaIndex;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table as SchemaTable;

const TOO_LONG_FOREIGN_KEY_NAME = 'fk_posts_author_account_reference_that_is_far_too_long_for_a_name';

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

    expect($table)
        ->toBeInstanceOf(SchemaTable::class)
        ->and($table->name)->toBe('posts')
        ->and($table->columns)->toHaveCount(2);
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

        /** @noinspection PhpUnused - Accessed via reflection */
        #[Column]
        public ?string $bio;

        #[Column(type: 'INT', default: 0)]
        public int $age;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->columns[0])
        ->toBeInstanceOf(SchemaColumn::class)
        ->and($table->columns[0]->name)->toBe('id')
        ->and($table->columns[0]->type)->toBe('integer')
        ->and($table->columns[0]->primaryKey)->toBeTrue()
        ->and($table->columns[0]->autoIncrement)->toBeTrue()
        ->and($table->columns[1]->name)->toBe('name')
        ->and($table->columns[1]->type)->toBe('varchar')
        ->and($table->columns[1]->length)->toBe(100)
        ->and($table->columns[2]->name)->toBe('email')
        ->and($table->columns[2]->unique)->toBeTrue()
        ->and($table->columns[3]->name)->toBe('bio')
        ->and($table->columns[3]->nullable)->toBeTrue()
        ->and($table->columns[4]->name)->toBe('age')
        ->and($table->columns[4]->default)->toBe(0);
});

it('converts IndexMetadata to Schema Index', function (): void {
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

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->indexes)
        ->toHaveCount(2)
        ->and($table->indexes[0])->toBeInstanceOf(SchemaIndex::class)
        ->and($table->indexes[0]->name)->toBe('posts_title_index')
        ->and($table->indexes[0]->columns)->toBe(['title'])
        ->and($table->indexes[0]->type)->toBe(IndexType::Btree)
        ->and($table->indexes[1]->name)->toBe('posts_slug_status_unique')
        ->and($table->indexes[1]->columns)->toBe(['slug', 'status'])
        ->and($table->indexes[1]->type)->toBe(IndexType::Unique);
});

it('builds a schema Index carrying the where predicate', function (): void {
    $entity = new #[Table('shows')]
    #[Index(['status'], where: "status = 'live'")]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $status;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($metadata->indexes[0]->where)->toBe("status = 'live'")
        ->and($table->indexes[0]->where)->toBe("status = 'live'");
});

it('carries unmanagedIndexes from the Table attribute to the schema Table', function (): void {
    $entity = new #[Table('shows', unmanagedIndexes: ['shows_live_partial_idx'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($metadata->unmanagedIndexes)->toBe(['shows_live_partial_idx'])
        ->and($table->unmanagedIndexes)->toBe(['shows_live_partial_idx']);
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

    expect($table->columns[1]->references)
        ->toBe('users.id')
        ->and($table->columns[1]->onDelete)->toBe('CASCADE')
        ->and($table->columns[1]->onUpdate)->toBe('SET NULL');
});

it('builds ForeignKey objects from column references', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id', onDelete: 'CASCADE', onUpdate: 'SET NULL')]
        public int $userId;
    };

    $metadata = $this->metadataFactory->parse($entity::class);
    $table = $this->schemaBuilder->build($metadata);

    expect($table->foreignKeys)
        ->toHaveCount(1)
        ->and($table->foreignKeys[0]->name)->toBe('fk_posts_user_id')
        ->and($table->foreignKeys[0]->columns)->toBe(['user_id'])
        ->and($table->foreignKeys[0]->referencedTable)->toBe('users')
        ->and($table->foreignKeys[0]->referencedColumns)->toBe(['id'])
        ->and($table->foreignKeys[0]->onDelete)->toBe('CASCADE')
        ->and($table->foreignKeys[0]->onUpdate)->toBe('SET NULL');
});

it('canonicalizes the int, bool and string type synonyms', function (): void {
    $entity = new #[Table('synonyms')] class () extends Entity
    {
        #[Column(type: 'int', primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(type: 'BOOL')]
        public bool $active;

        #[Column(type: 'string', length: 100)]
        public string $name;
    };

    $columns = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class))->columns;

    expect(array_map(static fn (SchemaColumn $column): string => $column->type, $columns))
        ->toBe(['integer', 'boolean', 'varchar']);
});

it('keeps other types as declared', function (): void {
    $entity = new #[Table('declared_types')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(type: 'bigint')]
        public int $views;

        #[Column(type: 'text')]
        public string $body;
    };

    $columns = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class))->columns;

    expect(array_map(static fn (SchemaColumn $column): string => $column->type, $columns))
        ->toBe(['integer', 'bigint', 'text']);
});

it('keeps a foreign key name that fits unchanged', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(references: 'users.id')]
        public int $userId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->foreignKeys[0]->name)->toBe('fk_posts_user_id');
});

it('shortens a foreign key name over 63 bytes', function (): void {
    $entity = new #[Table('customer_subscription_events')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(references: 'billing_accounts.id')]
        public int $billingAccountReferenceNumber;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    $full = 'fk_customer_subscription_events_billing_account_reference_number';

    expect($table->foreignKeys[0]->name)
        ->toBe('fk_customer_subscription_events_billing_account_refere_' . hash('crc32b', $full))
        ->and(strlen($table->foreignKeys[0]->name))->toBeLessThanOrEqual(63);
});

it('throws EntityException when a declared index name is longer than 63 bytes', function (): void {
    $entity = new #[Table('products')]
    #[Index(['sku'], name: 'idx_products_warehouse_location_code_and_supplier_reference_lookup_key')]
    class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 100)]
        public string $sku;
    };

    expect(fn () => $this->schemaBuilder->build($this->metadataFactory->parse($entity::class)))
        ->toThrow(EntityException::class);
});

it('names the entity, the index and its byte length in the over-long index name error', function (): void {
    $entity = new #[Table('products')]
    #[Index(['sku'], name: 'idx_products_warehouse_location_code_and_supplier_reference_lookup_key')]
    class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 100)]
        public string $sku;
    };

    $exception = null;

    try {
        $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())
        ->toContain('idx_products_warehouse_location_code_and_supplier_reference_lookup_key')
        ->toContain('70 bytes')
        ->toContain('63 bytes')
        ->and($exception->getContext())->toContain($entity::class)
        ->toContain("'products'")
        ->and($exception->getSuggestion())->toContain('#[Index(name:');
});

it('measures a declared multibyte index name in bytes', function (): void {
    // 32 characters, 64 bytes
    $entity = new #[Table('products')]
    #[Index(['sku'], name: 'éééééééééééééééééééééééééééééééé')]
    class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 100)]
        public string $sku;
    };

    expect(fn () => $this->schemaBuilder->build($this->metadataFactory->parse($entity::class)))
        ->toThrow(EntityException::class, '64 bytes');
});

it('accepts a declared index name of exactly 63 bytes', function (): void {
    $entity = new #[Table('products')]
    #[Index(['sku'], name: 'idx_products_warehouse_location_code_and_supplier_reference_key')]
    class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column(length: 100)]
        public string $sku;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->indexes[0]->name)->toBe('idx_products_warehouse_location_code_and_supplier_reference_key')
        ->and(strlen($table->indexes[0]->name))->toBe(63);
});

it('derives a plain index name from the table and columns', function (): void {
    $entity = new #[Table('posts')]
    #[Index(['author_id', 'status'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $authorId;

        #[Column]
        public string $status;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->indexes[0]->name)->toBe('posts_author_id_status_index')
        ->and($table->indexes[0]->type)->toBe(IndexType::Btree);
});

it('derives a unique index name from the table and columns', function (): void {
    $entity = new #[Table('users')]
    #[Index(['email'], unique: true)]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $email;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->indexes[0]->name)->toBe('users_email_unique')
        ->and($table->indexes[0]->type)->toBe(IndexType::Unique);
});

it('uses an explicit index name literally', function (): void {
    $entity = new #[Table('users')]
    #[Index(['email'], name: 'idx_users_email_lookup')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $email;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->indexes[0]->name)->toBe('idx_users_email_lookup');
});

it('throws when two indexes on one entity resolve to the same name', function (): void {
    $entity = new #[Table('users')]
    #[Index(['email'], where: 'active = true')]
    #[Index(['email'], where: 'deleted_at IS NULL')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public string $email;
    };

    $exception = null;

    try {
        $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())->toContain('users_email_index')
        ->and($exception->getContext())->toContain("'users'")
        ->toContain($entity::class)
        ->and($exception->getSuggestion())->toContain('name:');
});

it('shortens a long derived index name instead of rejecting it', function (): void {
    $entity = new #[Table('customer_subscription_events')]
    #[Index(['billing_account_reference_number', 'subscription_plan_identifier'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $billingAccountReferenceNumber;

        #[Column]
        public int $subscriptionPlanIdentifier;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    $body = 'customer_subscription_events_billing_account_reference_number_subscription_plan_identifier';

    expect($table->indexes[0]->name)->toBe(IdentifierName::derive($body, suffix: '_index'))
        ->and(strlen($table->indexes[0]->name))->toBeLessThanOrEqual(63)
        ->and($table->indexes[0]->name)->toContain(hash('crc32b', $body . '_index'));
});

it('builds a foreign key from a ForeignKey attribute', function (): void {
    $entity = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], onDelete: 'cascade', onUpdate: 'restrict')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->foreignKeys)->toHaveCount(1)
        ->and($table->foreignKeys[0]->name)->toBe('fk_posts_user_id')
        ->and($table->foreignKeys[0]->columns)->toBe(['user_id'])
        ->and($table->foreignKeys[0]->referencedTable)->toBe('users')
        ->and($table->foreignKeys[0]->referencedColumns)->toBe(['id'])
        ->and($table->foreignKeys[0]->onDelete)->toBe('cascade')
        ->and($table->foreignKeys[0]->onUpdate)->toBe('restrict');
});

it('builds the same foreign key from a ForeignKey attribute as from a column reference', function (): void {
    $fromReference = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id', onDelete: 'cascade', onUpdate: 'restrict')]
        public int $userId;
    };
    $fromAttribute = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], onDelete: 'cascade', onUpdate: 'restrict')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;
    };

    $referenceKey = $this->schemaBuilder->build($this->metadataFactory->parse($fromReference::class))->foreignKeys[0];
    $attributeKey = $this->schemaBuilder->build($this->metadataFactory->parse($fromAttribute::class))->foreignKeys[0];

    expect($attributeKey->equals($referenceKey))->toBeTrue();
});

it('builds a composite foreign key from a ForeignKey attribute', function (): void {
    $entity = new #[Table('order_lines')]
    #[ForeignKey(['order_id', 'item_id'], 'order_items', ['order_id', 'id'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $orderId;

        #[Column]
        public int $itemId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->foreignKeys)->toHaveCount(1)
        ->and($table->foreignKeys[0]->columns)->toBe(['order_id', 'item_id'])
        ->and($table->foreignKeys[0]->referencedTable)->toBe('order_items')
        ->and($table->foreignKeys[0]->referencedColumns)->toBe(['order_id', 'id']);
});

it('builds attribute and column reference foreign keys together', function (): void {
    $entity = new #[Table('posts')]
    #[ForeignKey(['team_id'], 'teams', ['id'], name: 'posts_team_fk')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id')]
        public int $userId;

        #[Column]
        public int $teamId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect(array_map(fn ($foreignKey) => $foreignKey->name, $table->foreignKeys))
        ->toBe(['fk_posts_user_id', 'posts_team_fk'])
        ->and($table->foreignKeys[1]->columns)->toBe(['team_id']);
});

it('derives a foreign key name from the table and columns when none is given', function (): void {
    $entity = new #[Table('order_lines')]
    #[ForeignKey(['order_id', 'item_id'], 'order_items', ['order_id', 'id'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $orderId;

        #[Column]
        public int $itemId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect($table->foreignKeys[0]->name)->toBe('fk_order_lines_order_id_item_id');
});

it('derives the same name for an unnamed single column ForeignKey as for a column reference', function (): void {
    $fromReference = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id')]
        public int $userId;
    };
    $fromAttribute = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;
    };

    $referenceName = $this->schemaBuilder->build(
        $this->metadataFactory->parse($fromReference::class),
    )->foreignKeys[0]->name;
    $attributeName = $this->schemaBuilder->build(
        $this->metadataFactory->parse($fromAttribute::class),
    )->foreignKeys[0]->name;

    expect($attributeName)->toBe($referenceName)
        ->toBe('fk_posts_user_id');
});

it('throws when a ForeignKey name is longer than 63 bytes', function (): void {
    $entity = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], name: TOO_LONG_FOREIGN_KEY_NAME)]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;
    };

    $exception = null;

    try {
        $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())
        ->toContain('fk_posts_author_account_reference_that_is_far_too_long_for_a_name')
        ->toContain('65 bytes')
        ->toContain('63 bytes')
        ->and($exception->getContext())->toContain($entity::class)
        ->toContain("'posts'")
        ->and($exception->getSuggestion())->toContain('#[ForeignKey(name:');
});

it('throws when two foreign keys on a table share a name', function (): void {
    $sharedName = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], name: 'posts_owner_fk')]
    #[ForeignKey(['team_id'], 'teams', ['id'], name: 'posts_owner_fk')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;

        #[Column]
        public int $teamId;
    };
    $derivedClash = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], name: 'fk_posts_user_id')]
    #[ForeignKey(['user_id'], 'users', ['id'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column]
        public int $userId;
    };

    $exception = null;

    try {
        $this->schemaBuilder->build($this->metadataFactory->parse($sharedName::class));
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())->toContain('posts_owner_fk')
        ->toContain("'posts'")
        ->and($exception->getContext())->toContain($sharedName::class)
        ->and($exception->getSuggestion())->toContain('name:')
        ->and(fn () => $this->schemaBuilder->build($this->metadataFactory->parse($derivedClash::class)))
        ->toThrow(EntityException::class, 'fk_posts_user_id');
});

it('throws when a column is given a foreign key both by references and by a ForeignKey attribute', function (): void {
    $entity = new #[Table('posts')]
    #[ForeignKey(['user_id'], 'users', ['id'], name: 'posts_user_custom_fk')]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'users.id')]
        public int $userId;
    };

    $exception = null;

    try {
        $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())->toContain("'user_id'")
        ->toContain("'posts'")
        ->and($exception->getContext())->toContain($entity::class)
        ->and($exception->getSuggestion())->toContain('references:');
});

it('allows a composite ForeignKey that includes a column with references', function (): void {
    $entity = new #[Table('order_lines')]
    #[ForeignKey(['order_id', 'item_id'], 'order_items', ['order_id', 'id'])]
    class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        #[Column(references: 'orders.id')]
        public int $orderId;

        #[Column]
        public int $itemId;
    };

    $table = $this->schemaBuilder->build($this->metadataFactory->parse($entity::class));

    expect(array_map(fn ($foreignKey) => $foreignKey->columns, $table->foreignKeys))
        ->toBe([['order_id'], ['order_id', 'item_id']]);
});

it(
    'throws when a column references value is not in table dot column format',
    function (string $references): void {
        $metadata = new EntityMetadata(
            entityClass: Entity::class,
            tableName: 'posts',
            primaryKey: 'id',
            columns: [
                new ColumnMetadata(name: 'id', type: 'int', primaryKey: true),
                new ColumnMetadata(name: 'user_id', type: 'int', references: $references),
            ],
        );
        $exception = null;

        try {
            $this->schemaBuilder->build($metadata);
        } catch (EntityException $caught) {
            $exception = $caught;
        }

        expect($exception)->toBeInstanceOf(EntityException::class)
            ->and($exception->getMessage())->toContain("'$references'")
            ->toContain("'user_id'")
            ->and($exception->getContext())->toContain(Entity::class)
            ->and($exception->getSuggestion())->toContain('table.column');
    },
)->with(['users', 'users.', '.id', 'public.users.id']);

it('throws when an extender column references value is not in table dot column format', function (): void {
    $columns = [new ColumnMetadata(name: 'team_id', type: 'int', references: 'teams')];
    $exception = null;

    try {
        $this->schemaBuilder->buildForeignKeysForTable(Entity::class, 'posts', $columns);
    } catch (EntityException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(EntityException::class)
        ->and($exception->getMessage())->toContain("'teams'")
        ->toContain("'team_id'")
        ->and($exception->getContext())->toContain(Entity::class)
        ->and($exception->getSuggestion())->toContain('table.column');
});
