<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use BackedEnum;
use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityExtension;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\ExtensionMetadata;
use Marko\Database\Entity\PropertyMetadata;

#[Table('users')]
class HydratorTestUser extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $name;

    #[Column('email_address')]
    public string $email;

    #[Column]
    public bool $isActive;

    #[Column]
    public ?string $bio = null;
}

#[Table('posts')]
class HydratorTestPost extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $title;

    #[Column]
    public DateTimeImmutable $createdAt;

    #[Column]
    public ?DateTimeImmutable $publishedAt = null;
}

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}

#[Table('articles')]
class HydratorTestArticle extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $title;

    #[Column]
    public PostStatus $status;

    #[Column]
    public ?PostStatus $previousStatus = null;
}

#[Table('items')]
class HydratorSnakeCaseEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public string $firstName;

    #[Column]
    public string $lastName;

    #[Column]
    public int $totalCount;
}

// ── Extension fixtures ─────────────────────────────────────────────────────────

class HydratorExtProfile extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public string $phone;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public ?string $website = null;
}

class HydratorExtMetrics extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $viewCount;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public float $rating;
}

enum HydratorExtStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class HydratorExtTyped extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public ?string $nullableField = null;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public array $jsonField;

    /** @noinspection PhpUnused - Entity property for structural definition */
    public HydratorExtStatus $statusField;
}

// ── Tests ──────────────────────────────────────────────────────────────────────

it('creates EntityHydrator class', function (): void {
    $hydrator = new EntityHydrator();
    expect($hydrator)->toBeInstanceOf(EntityHydrator::class);
});

it('hydrates entity from database row array', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => 'A developer',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity)
        ->toBeInstanceOf(HydratorTestUser::class)
        ->and($entity->id)->toBe(1)
        ->and($entity->name)->toBe('John Doe')
        ->and($entity->email)->toBe('john@example.com')
        ->and($entity->isActive)->toBeTrue()
        ->and($entity->bio)->toBe('A developer');
});

it('maps database columns to entity properties using metadata', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => 5,
        'name' => 'Jane',
        'email_address' => 'jane@example.com',
        'is_active' => 0,
        'bio' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->email)
        ->toBe('jane@example.com')
        ->and($entity->isActive)->toBeFalse();
});

it('handles snake_case to camelCase conversion', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createSnakeCaseMetadata();

    $row = [
        'id' => 1,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'total_count' => 42,
    ];

    $entity = $hydrator->hydrate(HydratorSnakeCaseEntity::class, $row, $metadata);

    expect($entity->firstName)
        ->toBe('John')
        ->and($entity->lastName)->toBe('Doe')
        ->and($entity->totalCount)->toBe(42);
});

it('converts database types to PHP types (int, string, bool)', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => '123',
        'name' => 'Test User',
        'email_address' => 'test@example.com',
        'is_active' => '1',
        'bio' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->id)
        ->toBe(123)
        ->toBeInt()
        ->and($entity->isActive)->toBeTrue()
        ->toBeBool();
});

it('converts datetime strings to DateTimeImmutable', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createPostMetadata();

    $row = [
        'id' => 1,
        'title' => 'Test Post',
        'created_at' => '2024-01-15 10:30:00',
        'published_at' => '2024-01-16 12:00:00',
    ];

    $entity = $hydrator->hydrate(HydratorTestPost::class, $row, $metadata);

    expect($entity->createdAt)
        ->toBeInstanceOf(DateTimeImmutable::class)
        ->and($entity->createdAt->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00')
        ->and($entity->publishedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($entity->publishedAt->format('Y-m-d H:i:s'))->toBe('2024-01-16 12:00:00');
});

it('converts enum values to BackedEnum instances', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createArticleMetadata();

    $row = [
        'id' => 1,
        'title' => 'Test Article',
        'status' => 'published',
        'previous_status' => 'draft',
    ];

    $entity = $hydrator->hydrate(HydratorTestArticle::class, $row, $metadata);

    expect($entity->status)
        ->toBe(PostStatus::Published)
        ->toBeInstanceOf(BackedEnum::class)
        ->and($entity->previousStatus)->toBe(PostStatus::Draft);
});

it('handles nullable properties correctly', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => null,
        'name' => 'Test',
        'email_address' => 'test@example.com',
        'is_active' => 1,
        'bio' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->id)
        ->toBeNull()
        ->and($entity->bio)->toBeNull();

    $postMetadata = createPostMetadata();
    $postRow = [
        'id' => 1,
        'title' => 'Test',
        'created_at' => '2024-01-15 10:30:00',
        'published_at' => null,
    ];

    $post = $hydrator->hydrate(HydratorTestPost::class, $postRow, $postMetadata);
    expect($post->publishedAt)->toBeNull();

    $articleMetadata = createArticleMetadata();
    $articleRow = [
        'id' => 1,
        'title' => 'Test',
        'status' => 'draft',
        'previous_status' => null,
    ];

    $article = $hydrator->hydrate(HydratorTestArticle::class, $articleRow, $articleMetadata);
    expect($article->previousStatus)->toBeNull();
});

it('extracts entity data to row array for persistence', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 1;
    $entity->name = 'John Doe';
    $entity->email = 'john@example.com';
    $entity->isActive = true;
    $entity->bio = 'Developer';

    $row = $hydrator->extract($entity, $metadata);

    expect($row)->toBe([
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => true,
        'bio' => 'Developer',
    ]);
});

it('tracks whether entity is new (no ID) or persisted (has ID)', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $newEntity = new HydratorTestUser();
    $newEntity->name = 'New User';
    $newEntity->email = 'new@example.com';
    $newEntity->isActive = true;

    expect($hydrator->isNew($newEntity, $metadata))->toBeTrue();

    $persistedEntity = new HydratorTestUser();
    $persistedEntity->id = 5;
    $persistedEntity->name = 'Existing User';
    $persistedEntity->email = 'existing@example.com';
    $persistedEntity->isActive = true;

    expect($hydrator->isNew($persistedEntity, $metadata))->toBeFalse();
});

it('preserves original values for dirty checking', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $originalValues = $hydrator->getOriginalValues($entity);

    expect($originalValues)->toBe([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'isActive' => true,
        'bio' => null,
    ]);
});

it('detects changed properties via isDirty()', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($hydrator->isDirty($entity, $metadata))->toBeFalse();

    $entity->name = 'Jane Doe';
    expect($hydrator->isDirty($entity, $metadata))->toBeTrue();

    $dirtyProperties = $hydrator->getDirtyProperties($entity, $metadata);
    expect($dirtyProperties)->toBe(['name']);
});

it('registers original values for an entity with initialized properties', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 1;
    $entity->name = 'Alice';
    $entity->email = 'alice@example.com';
    $entity->isActive = true;
    $entity->bio = 'Engineer';

    $hydrator->registerOriginalValues($entity, $metadata);

    $originalValues = $hydrator->getOriginalValues($entity);

    expect($originalValues)->toBe([
        'id' => 1,
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'isActive' => true,
        'bio' => 'Engineer',
    ]);
});

it('makes getDirtyProperties return empty array immediately after registration', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 2;
    $entity->name = 'Bob';
    $entity->email = 'bob@example.com';
    $entity->isActive = false;
    $entity->bio = null;

    $hydrator->registerOriginalValues($entity, $metadata);

    expect($hydrator->getDirtyProperties($entity, $metadata))->toBeEmpty();
});

it('detects a property as dirty after mutation post-registration', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 3;
    $entity->name = 'Carol';
    $entity->email = 'carol@example.com';
    $entity->isActive = true;
    $entity->bio = null;

    $hydrator->registerOriginalValues($entity, $metadata);

    $entity->name = 'Caroline';

    expect($hydrator->getDirtyProperties($entity, $metadata))->toBe(['name']);
});

it('skips uninitialized properties when registering', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->name = 'Dave';
    // email and isActive are not set (uninitialized, no default)
    // id has default null, bio has default null

    $hydrator->registerOriginalValues($entity, $metadata);

    $originalValues = $hydrator->getOriginalValues($entity);

    expect($originalValues)
        ->toHaveKey('name')
        ->not->toHaveKey('email')
        ->not->toHaveKey('isActive');
});

it('replaces prior original values when called a second time', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 4;
    $entity->name = 'Eve';
    $entity->email = 'eve@example.com';
    $entity->isActive = true;
    $entity->bio = null;

    $hydrator->registerOriginalValues($entity, $metadata);

    $entity->name = 'Evelyn';

    $hydrator->registerOriginalValues($entity, $metadata);

    expect($hydrator->getDirtyProperties($entity, $metadata))->toBeEmpty()
        ->and($hydrator->getOriginalValues($entity)['name'])->toBe('Evelyn');
});

it('snapshots values by value, not by reference', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $entity = new HydratorTestUser();
    $entity->id = 5;
    $entity->name = 'Frank';
    $entity->email = 'frank@example.com';
    $entity->isActive = true;
    $entity->bio = null;

    $hydrator->registerOriginalValues($entity, $metadata);

    $entity->name = 'Franklin';

    $originalValues = $hydrator->getOriginalValues($entity);
    expect($originalValues['name'])->toBe('Frank');
});

// Helper functions to create metadata

function createUserMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestUser::class,
        tableName: 'users',
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
            'name' => new PropertyMetadata(
                name: 'name',
                columnName: 'name',
                type: 'string',
                nullable: false,
            ),
            'email' => new PropertyMetadata(
                name: 'email',
                columnName: 'email_address',
                type: 'string',
                nullable: false,
            ),
            'isActive' => new PropertyMetadata(
                name: 'isActive',
                columnName: 'is_active',
                type: 'bool',
                nullable: false,
            ),
            'bio' => new PropertyMetadata(
                name: 'bio',
                columnName: 'bio',
                type: 'string',
                nullable: true,
            ),
        ],
    );
}

function createPostMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestPost::class,
        tableName: 'posts',
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
            'title' => new PropertyMetadata(
                name: 'title',
                columnName: 'title',
                type: 'string',
                nullable: false,
            ),
            'createdAt' => new PropertyMetadata(
                name: 'createdAt',
                columnName: 'created_at',
                type: DateTimeImmutable::class,
                nullable: false,
            ),
            'publishedAt' => new PropertyMetadata(
                name: 'publishedAt',
                columnName: 'published_at',
                type: DateTimeImmutable::class,
                nullable: true,
            ),
        ],
    );
}

function createArticleMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestArticle::class,
        tableName: 'articles',
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
            'title' => new PropertyMetadata(
                name: 'title',
                columnName: 'title',
                type: 'string',
                nullable: false,
            ),
            'status' => new PropertyMetadata(
                name: 'status',
                columnName: 'status',
                type: PostStatus::class,
                nullable: false,
                enumClass: PostStatus::class,
            ),
            'previousStatus' => new PropertyMetadata(
                name: 'previousStatus',
                columnName: 'previous_status',
                type: PostStatus::class,
                nullable: true,
                enumClass: PostStatus::class,
            ),
        ],
    );
}

function createSnakeCaseMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorSnakeCaseEntity::class,
        tableName: 'items',
        primaryKey: 'id',
        properties: [
            'id' => new PropertyMetadata(
                name: 'id',
                columnName: 'id',
                type: 'int',
                nullable: false,
                isPrimaryKey: true,
            ),
            'firstName' => new PropertyMetadata(
                name: 'firstName',
                columnName: 'first_name',
                type: 'string',
                nullable: false,
            ),
            'lastName' => new PropertyMetadata(
                name: 'lastName',
                columnName: 'last_name',
                type: 'string',
                nullable: false,
            ),
            'totalCount' => new PropertyMetadata(
                name: 'totalCount',
                columnName: 'total_count',
                type: 'int',
                nullable: false,
            ),
        ],
    );
}

function createProfileExtensionMetadata(): ExtensionMetadata
{
    return new ExtensionMetadata(
        extensionClass: HydratorExtProfile::class,
        entityClass: HydratorTestUser::class,
        properties: [
            'phone' => new PropertyMetadata(
                name: 'phone',
                columnName: 'phone',
                type: 'string',
                nullable: false,
            ),
            'website' => new PropertyMetadata(
                name: 'website',
                columnName: 'website',
                type: 'string',
                nullable: true,
            ),
        ],
    );
}

function createMetricsExtensionMetadata(): ExtensionMetadata
{
    return new ExtensionMetadata(
        extensionClass: HydratorExtMetrics::class,
        entityClass: HydratorTestUser::class,
        properties: [
            'viewCount' => new PropertyMetadata(
                name: 'viewCount',
                columnName: 'view_count',
                type: 'int',
                nullable: false,
            ),
            'rating' => new PropertyMetadata(
                name: 'rating',
                columnName: 'rating',
                type: 'float',
                nullable: false,
            ),
        ],
    );
}

function createTypedExtensionMetadata(): ExtensionMetadata
{
    return new ExtensionMetadata(
        extensionClass: HydratorExtTyped::class,
        entityClass: HydratorTestUser::class,
        properties: [
            'nullableField' => new PropertyMetadata(
                name: 'nullableField',
                columnName: 'nullable_field',
                type: 'string',
                nullable: true,
            ),
            'jsonField' => new PropertyMetadata(
                name: 'jsonField',
                columnName: 'json_field',
                type: 'array',
                nullable: false,
                columnType: 'json',
            ),
            'statusField' => new PropertyMetadata(
                name: 'statusField',
                columnName: 'status_field',
                type: HydratorExtStatus::class,
                nullable: false,
                enumClass: HydratorExtStatus::class,
            ),
        ],
    );
}

function createUserMetadataWithProfileExtension(): EntityMetadata
{
    $base = createUserMetadata();

    return new EntityMetadata(
        entityClass: $base->entityClass,
        tableName: $base->tableName,
        primaryKey: $base->primaryKey,
        properties: $base->properties,
        extensions: [
            HydratorExtProfile::class => createProfileExtensionMetadata(),
        ],
    );
}

function createUserMetadataWithBothExtensions(): EntityMetadata
{
    $base = createUserMetadata();

    return new EntityMetadata(
        entityClass: $base->entityClass,
        tableName: $base->tableName,
        primaryKey: $base->primaryKey,
        properties: $base->properties,
        extensions: [
            HydratorExtProfile::class => createProfileExtensionMetadata(),
            HydratorExtMetrics::class => createMetricsExtensionMetadata(),
        ],
    );
}

function createUserMetadataWithTypedExtension(): EntityMetadata
{
    $base = createUserMetadata();

    return new EntityMetadata(
        entityClass: $base->entityClass,
        tableName: $base->tableName,
        primaryKey: $base->primaryKey,
        properties: $base->properties,
        extensions: [
            HydratorExtTyped::class => createTypedExtensionMetadata(),
        ],
    );
}

// ── Extension hydration tests ──────────────────────────────────────────────────

it('attaches a hydrated extension instance to the entity when its columns are in the row', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        'website' => 'https://example.com',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->extension(HydratorExtProfile::class))->toBeInstanceOf(HydratorExtProfile::class);
});

it('hydrates extension property values from the DB row', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        'website' => 'https://example.com',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $profile = $entity->extension(HydratorExtProfile::class);

    expect($profile->phone)->toBe('555-1234')
        ->and($profile->website)->toBe('https://example.com');
});

it('skips attaching an extension when none of its columns are present in the row', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        // no phone or website columns
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->extension(HydratorExtProfile::class))->toBeNull();
});

it('partially hydrates an extension when only some of its columns are present in the row', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        // website column is absent
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $profile = $entity->extension(HydratorExtProfile::class);

    expect($profile)->toBeInstanceOf(HydratorExtProfile::class)
        ->and($profile->phone)->toBe('555-1234');
});

it('hydrates multiple extensions from the same row independently', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithBothExtensions();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        'website' => 'https://example.com',
        'view_count' => 42,
        'rating' => 4.5,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $profile = $entity->extension(HydratorExtProfile::class);
    $metrics = $entity->extension(HydratorExtMetrics::class);

    expect($profile)->toBeInstanceOf(HydratorExtProfile::class)
        ->and($metrics)->toBeInstanceOf(HydratorExtMetrics::class)
        ->and($profile->phone)->toBe('555-1234')
        ->and($metrics->viewCount)->toBe(42);
});

it('converts extension column values to the correct PHP types', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithBothExtensions();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => 555,
        'website' => null,
        'view_count' => '99',
        'rating' => '3.7',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $profile = $entity->extension(HydratorExtProfile::class);
    $metrics = $entity->extension(HydratorExtMetrics::class);

    expect($profile->phone)->toBe('555')
        ->toBeString()
        ->and($metrics->viewCount)->toBe(99)
        ->toBeInt()
        ->and($metrics->rating)->toBe(3.7)
        ->toBeFloat();
});

it('converts nullable extension column values to null', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        'website' => null,
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $profile = $entity->extension(HydratorExtProfile::class);

    expect($profile->website)->toBeNull();
});

it('converts JSON extension column values to arrays', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithTypedExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'nullable_field' => 'hello',
        'json_field' => '{"key":"value","count":3}',
        'status_field' => 'active',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $typed = $entity->extension(HydratorExtTyped::class);

    expect($typed->jsonField)->toBe(['key' => 'value', 'count' => 3])
        ->toBeArray();
});

it('converts BackedEnum extension column values via the enum class', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithTypedExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'nullable_field' => null,
        'json_field' => '[]',
        'status_field' => 'inactive',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $typed = $entity->extension(HydratorExtTyped::class);

    expect($typed->statusField)->toBe(HydratorExtStatus::Inactive)
        ->toBeInstanceOf(BackedEnum::class);
});

it('does not affect base entity hydration when no extensions are registered', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadata();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => 'Developer',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);

    expect($entity->id)->toBe(1)
        ->and($entity->name)->toBe('John Doe')
        ->and($entity->email)->toBe('john@example.com')
        ->and($entity->isActive)->toBeTrue()
        ->and($entity->bio)->toBe('Developer')
        ->and($entity->extension(HydratorExtProfile::class))->toBeNull();
});

it('does not include extension property values in the originalValues dirty-tracking map', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createUserMetadataWithProfileExtension();

    $row = [
        'id' => 1,
        'name' => 'John Doe',
        'email_address' => 'john@example.com',
        'is_active' => 1,
        'bio' => null,
        'phone' => '555-1234',
        'website' => 'https://example.com',
    ];

    $entity = $hydrator->hydrate(HydratorTestUser::class, $row, $metadata);
    $originalValues = $hydrator->getOriginalValues($entity);

    expect($originalValues)->toHaveKeys(['id', 'name', 'email', 'isActive', 'bio'])
        ->not->toHaveKey('phone')
        ->not->toHaveKey('website');
});
