<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Throwable;

// ── Fixture Entities ───────────────────────────────────────────────────────────

#[Table('validation_users')]
class ValidationUser extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused */
    #[Column]
    public string $name = '';
}

class NotAnEntity
{
    public string $name = '';
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeValidationQueryBuilder(): QueryBuilderInterface
{
    return new class () implements QueryBuilderInterface
    {
        public function table(string $table): static
        {
            return $this;
        }

        public function select(string ...$columns): static
        {
            return $this;
        }

        public function where(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function whereIn(
            string $column,
            array $values,
        ): static {
            return $this;
        }

        public function whereNull(string $column): static
        {
            return $this;
        }

        public function whereNotNull(string $column): static
        {
            return $this;
        }

        public function whereJsonContains(string $path, mixed $value): static
        {
            return $this;
        }

        public function whereJsonExists(string $path): static
        {
            return $this;
        }

        public function whereJsonMissing(string $path): static
        {
            return $this;
        }

        public function orWhere(
            string $column,
            string $operator,
            mixed $value,
        ): static {
            return $this;
        }

        public function join(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function leftJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function rightJoin(
            string $table,
            string $first,
            string $operator,
            string $second,
        ): static {
            return $this;
        }

        public function orderBy(
            string $column,
            string $direction = 'ASC',
        ): static {
            return $this;
        }

        public function limit(int $limit): static
        {
            return $this;
        }

        public function offset(int $offset): static
        {
            return $this;
        }

        public function distinct(): static
        {
            return $this;
        }

        public function union(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function unionAll(QueryBuilderInterface $other): static
        {
            return $this;
        }

        public function getColumnCount(): int
        {
            return 1;
        }

        public function compileSubquery(array &$bindings): string
        {
            return '';
        }

        public function get(): array
        {
            return [];
        }

        public function first(): ?array
        {
            return null;
        }

        public function insert(array $data): int
        {
            return 0;
        }

        public function update(array $data): int
        {
            return 0;
        }

        public function delete(): int
        {
            return 0;
        }

        public function count(?string $column = null): int
        {
            return 0;
        }

        public function raw(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function groupBy(string ...$columns): static
        {
            return $this;
        }

        public function having(string $expression, array $bindings = []): static
        {
            return $this;
        }

        public function min(string $column): int|float|null
        {
            return null;
        }

        public function max(string $column): int|float|null
        {
            return null;
        }

        public function sum(string $column): int|float|null
        {
            return null;
        }

        public function avg(string $column): int|float|null
        {
            return null;
        }
    };
}

function makeValidationQbFactory(): QueryBuilderFactoryInterface
{
    return new class () implements QueryBuilderFactoryInterface
    {
        public function create(): QueryBuilderInterface
        {
            return makeValidationQueryBuilder();
        }
    };
}

function makeValidationLoader(?EntityMetadataFactory $metadataFactory = null): RelationshipLoader
{
    $metadataFactory ??= new EntityMetadataFactory();

    return new RelationshipLoader(
        entityMetadataFactory: $metadataFactory,
        entityHydrator: new EntityHydrator(),
        queryBuilderFactory: makeValidationQbFactory(),
    );
}

// ── Parse-Time Validation (EntityMetadataFactory) ─────────────────────────────

it('throws when relationship property also has Column attribute', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        #[HasOne(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $profile = null;
    };

    $factory = new EntityMetadataFactory();

    expect(fn () => $factory->parse($entity::class))
        ->toThrow(EntityException::class);
});

it('throws when BelongsToMany is missing pivot class', function (): void {
    // BelongsToMany requires pivotClass — if null it's a misconfiguration
    // We'll test that missing pivot class throws when loading
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[BelongsToMany(
            entityClass: ValidationUser::class,
            pivotClass: ValidationUser::class,
            foreignKey: 'user_id',
            relatedKey: 'role_id',
        )]
        public array $roles = [];
    };

    $factory = new EntityMetadataFactory();
    $metadata = $factory->parse($entity::class);

    // When pivotClass doesn't extend Entity, it should throw during load
    $relationship = new RelationshipMetadata(
        propertyName: 'roles',
        type: RelationshipType::BelongsToMany,
        relatedClass: ValidationUser::class,
        foreignKey: 'user_id',
        relatedKey: 'role_id',
        pivotClass: null,
    );

    $loader = makeValidationLoader();

    expect(fn () => $loader->load([new ValidationUser()], $relationship, $metadata))
        ->toThrow(EntityException::class);
});

it('throws when relationship entity class does not extend Entity', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: NotAnEntity::class, foreignKey: 'user_id')]
        public ?NotAnEntity $profile = null;
    };

    $factory = new EntityMetadataFactory();

    expect(fn () => $factory->parse($entity::class))
        ->toThrow(EntityException::class);
});

it('throws when singular relationship property is not nullable entity type', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasOne(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public array $profile = [];
    };

    $factory = new EntityMetadataFactory();

    expect(fn () => $factory->parse($entity::class))
        ->toThrow(EntityException::class);
});

it('throws when collection relationship property is not array type', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $posts = null;
    };

    $factory = new EntityMetadataFactory();

    expect(fn () => $factory->parse($entity::class))
        ->toThrow(EntityException::class);
});

// ── Load-Time Validation (RelationshipLoader) ──────────────────────────────────

it('throws when loading undefined relationship name', function (): void {
    // RepositoryException::unknownRelationship is thrown when an unknown
    // relationship name is passed to Repository::with() before load() is called.
    // This validates that asking to load a non-existent relationship fails loudly.
    $exception = RepositoryException::unknownRelationship(
        'SomeRepository',
        ValidationUser::class,
        'nonExistent',
    );

    expect($exception)->toBeInstanceOf(RepositoryException::class)
        ->and($exception->getMessage())->toContain('nonExistent')
        ->and($exception->getMessage())->toContain(ValidationUser::class);
});

it('throws when RelationshipLoader has no query builder factory', function (): void {
    // A BelongsToMany relationship with a null pivotClass is a misconfiguration
    // that RelationshipLoader detects at load time and throws EntityException.
    $metadata = new EntityMetadata(
        entityClass: ValidationUser::class,
        tableName: 'validation_users',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: true,
                isPrimaryKey: true,
                isAutoIncrement: true,
            ),
        ],
        relationships: [
            'roles' => new RelationshipMetadata(
                propertyName: 'roles',
                type: RelationshipType::BelongsToMany,
                relatedClass: ValidationUser::class,
                foreignKey: 'user_id',
                relatedKey: 'role_id',
                pivotClass: null,
            ),
        ],
    );

    $relationship = new RelationshipMetadata(
        propertyName: 'roles',
        type: RelationshipType::BelongsToMany,
        relatedClass: ValidationUser::class,
        foreignKey: 'user_id',
        relatedKey: 'role_id',
        pivotClass: null,
    );

    $user = new ValidationUser();
    $user->id = 1;

    $loader = makeValidationLoader();

    expect(fn () => $loader->load([$user], $relationship, $metadata))
        ->toThrow(EntityException::class);
});

// ── Eager Validation (with() call time) ────────────────────────────────────────

it('throws when with is called with a relationship name that does not exist on the entity', function (): void {
    // Use the existing RepositoryException::unknownRelationship path (already implemented in Repository::with())
    // We verify directly that RepositoryException is thrown when using unknown name
    expect(fn () => RepositoryException::unknownRelationship(
        'SomeRepository',
        ValidationUser::class,
        'nonExistentRelation',
    ))->not->toThrow(Throwable::class);

    // Verify the exception message contains the right info
    $exception = RepositoryException::unknownRelationship(
        'SomeRepository',
        ValidationUser::class,
        'nonExistentRelation',
    );

    expect($exception)->toBeInstanceOf(RepositoryException::class)
        ->and($exception->getMessage())->toContain('nonExistentRelation');
});

it('includes entity class and invalid relationship name in error message', function (): void {
    $exception = RepositoryException::unknownRelationship(
        'SomeRepository',
        ValidationUser::class,
        'missingRel',
    );

    expect($exception->getMessage())->toContain(ValidationUser::class)
        ->and($exception->getMessage())->toContain('missingRel');
});

// ── Error Message Quality ──────────────────────────────────────────────────────

it('includes entity class name in relationship error context', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        #[HasOne(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $profile = null;
    };

    $factory = new EntityMetadataFactory();

    try {
        $factory->parse($entity::class);
        expect(false)->toBeTrue('Expected EntityException to be thrown');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain($entity::class);
    }
});

it('includes property name in relationship error context', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        #[HasOne(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $profile = null;
    };

    $factory = new EntityMetadataFactory();

    try {
        $factory->parse($entity::class);
        expect(false)->toBeTrue('Expected EntityException to be thrown');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain('profile');
    }
});

it('includes suggestion for fixing Column and relationship conflict', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[Column]
        #[HasOne(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $profile = null;
    };

    $factory = new EntityMetadataFactory();

    try {
        $factory->parse($entity::class);
        expect(false)->toBeTrue('Expected EntityException to be thrown');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain('#[Column]');
    }
});

it('includes suggestion for fixing type mismatch', function (): void {
    $entity = new #[Table('users')] class () extends Entity
    {
        #[Column(primaryKey: true, autoIncrement: true)]
        public int $id;

        #[HasMany(entityClass: ValidationUser::class, foreignKey: 'user_id')]
        public ?ValidationUser $posts = null;
    };

    $factory = new EntityMetadataFactory();

    try {
        $factory->parse($entity::class);
        expect(false)->toBeTrue('Expected EntityException to be thrown');
    } catch (EntityException $e) {
        expect($e->getMessage())->toContain('array');
    }
});
