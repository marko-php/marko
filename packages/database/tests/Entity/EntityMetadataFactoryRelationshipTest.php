<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;

beforeEach(function (): void {
    $this->factory = new EntityMetadataFactory();
});

// ── EntityMetadata Extension ──────────────────────────────────────────────────

it('includes relationships array in entity metadata', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toBeArray();
});

it('returns empty relationships array when entity has no relationships', function (): void {
    $entity = new #[Table('tags')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public string $name;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toBe([]);
});

it('provides getRelationship method to retrieve by property name', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->getRelationship('profile'))->toBeInstanceOf(RelationshipMetadata::class);
});

it('returns null from getRelationship for non-existent relationship', function (): void {
    $entity = new #[Table('tags')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->getRelationship('nonExistent'))->toBeNull();
});

it('provides getRelationships method returning all relationships', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public array $posts = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->getRelationships())->toHaveCount(2);
});

// ── Parsing HasOne ────────────────────────────────────────────────────────────

it('parses HasOne attribute from entity property', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('profile');
});

it('extracts entity class from HasOne attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['profile']->relatedClass)->toBe(Entity::class);
});

it('extracts foreign key from HasOne attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['profile']->foreignKey)->toBe('user_id');
});

it('sets relationship type to HasOne', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['profile']->type)->toBe(RelationshipType::HasOne);
});

// ── Parsing HasMany ───────────────────────────────────────────────────────────

it('parses HasMany attribute from entity property', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public array $posts = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('posts');
});

it('extracts entity class from HasMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public array $posts = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['posts']->relatedClass)->toBe(Entity::class);
});

it('extracts foreign key from HasMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public array $posts = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['posts']->foreignKey)->toBe('user_id');
});

it('sets relationship type to HasMany', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public array $posts = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['posts']->type)->toBe(RelationshipType::HasMany);
});

// ── Parsing BelongsTo ─────────────────────────────────────────────────────────

it('parses BelongsTo attribute from entity property', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public int $userId;

        #[BelongsTo(entityClass: Entity::class, foreignKey: 'userId')]
        public ?Entity $author = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('author');
});

it('extracts entity class from BelongsTo attribute', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public int $userId;

        #[BelongsTo(entityClass: Entity::class, foreignKey: 'userId')]
        public ?Entity $author = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['author']->relatedClass)->toBe(Entity::class);
});

it('extracts foreign key from BelongsTo attribute', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public int $userId;

        #[BelongsTo(entityClass: Entity::class, foreignKey: 'userId')]
        public ?Entity $author = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['author']->foreignKey)->toBe('userId');
});

it('sets relationship type to BelongsTo', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public int $userId;

        #[BelongsTo(entityClass: Entity::class, foreignKey: 'userId')]
        public ?Entity $author = null;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['author']->type)->toBe(RelationshipType::BelongsTo);
});

// ── Parsing BelongsToMany ─────────────────────────────────────────────────────

it('parses BelongsToMany attribute from entity property', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('roles');
});

it('extracts entity class from BelongsToMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['roles']->relatedClass)->toBe(Entity::class);
});

it('extracts pivot class from BelongsToMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['roles']->pivotClass)->toBe(Entity::class);
});

it('extracts foreign key from BelongsToMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['roles']->foreignKey)->toBe('user_id');
});

it('extracts related key from BelongsToMany attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['roles']->relatedKey)->toBe('role_id');
});

it('sets relationship type to BelongsToMany', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public array $roles = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships['roles']->type)->toBe(RelationshipType::BelongsToMany);
});

// ── Validation ────────────────────────────────────────────────────────────────

it('skips properties without relationship attributes', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        public string $computed = 'ignored';
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toBe([]);
});

it('parses entities with both columns and relationships', function (): void {
    $entity = new #[Table('posts')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        public string $title;

        #[Column]
        public int $userId;

        #[BelongsTo(entityClass: Entity::class, foreignKey: 'userId')]
        public ?Entity $author = null;

        #[HasMany(entityClass: Entity::class, foreignKey: 'post_id')]
        public array $comments = [];
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->columns)->toHaveCount(3)
        ->and($metadata->relationships)->toHaveCount(2);
});

it('accepts EntityCollection type for HasMany property', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: Entity::class, foreignKey: 'user_id')]
        public EntityCollection $posts;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('posts')
        ->and($metadata->relationships['posts']->type)->toBe(RelationshipType::HasMany);
});

it('accepts EntityCollection type for BelongsToMany property', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(entityClass: Entity::class, pivotClass: Entity::class, foreignKey: 'user_id', relatedKey: 'role_id')]
        public EntityCollection $roles;
    };

    $metadata = $this->factory->parse($entity::class);

    expect($metadata->relationships)->toHaveKey('roles')
        ->and($metadata->relationships['roles']->type)->toBe(RelationshipType::BelongsToMany);
});

it('caches relationship metadata with entity metadata', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: Entity::class, foreignKey: 'user_id')]
        public ?Entity $profile = null;
    };

    $metadata1 = $this->factory->parse($entity::class);
    $metadata2 = $this->factory->parse($entity::class);

    expect($metadata1)->toBe($metadata2)
        ->and($metadata1->relationships)->toBe($metadata2->relationships);
});
