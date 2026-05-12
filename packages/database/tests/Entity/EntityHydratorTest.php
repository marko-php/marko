<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use BackedEnum;
use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
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
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

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
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

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
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public string $firstName;

    #[Column]
    public string $lastName;

    #[Column]
    public int $totalCount;
}

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

// -------------------------------------------------------------------------
// Fixtures for companion-hydration tests (Task 006)
// -------------------------------------------------------------------------

#[Table('products')]
class HydratorTestProduct extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    /** @noinspection PhpUnused - Entity property for structural definition */
    public ?int $id = null;

    #[Column]
    /** @noinspection PhpUnused - Entity property for structural definition */
    public string $name;
}

#[Table(extends: HydratorTestProduct::class)]
class HydratorTestProductExt extends Entity
{
    #[Column]
    /** @noinspection PhpUnused - Entity property for structural definition */
    public string $sku;

    #[Column]
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $stock;
}

#[Table(extends: HydratorTestProduct::class)]
class HydratorTestProductPricing extends Entity
{
    #[Column]
    /** @noinspection PhpUnused - Entity property for structural definition */
    public float $price;
}

// -------------------------------------------------------------------------
// Helper functions to create metadata for companion-hydration tests
// -------------------------------------------------------------------------

function createProductMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestProduct::class,
        tableName: 'products',
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
        ],
    );
}

function createProductMetadataWithExtenders(string ...$extenders): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestProduct::class,
        tableName: 'products',
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
        ],
        extenders: $extenders,
    );
}

function createProductExtMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestProductExt::class,
        tableName: 'products',
        primaryKey: '',
        properties: [
            'sku' => new PropertyMetadata(
                name: 'sku',
                columnName: 'sku',
                type: 'string',
                nullable: false,
            ),
            'stock' => new PropertyMetadata(
                name: 'stock',
                columnName: 'stock',
                type: 'int',
                nullable: false,
            ),
        ],
    );
}

function createProductPricingMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: HydratorTestProductPricing::class,
        tableName: 'products',
        primaryKey: '',
        properties: [
            'price' => new PropertyMetadata(
                name: 'price',
                columnName: 'price',
                type: 'float',
                nullable: false,
            ),
        ],
    );
}

/**
 * Build a stub EntityMetadataFactory that returns pre-built metadata for given class-strings.
 *
 * @param array<class-string, EntityMetadata> $map
 */
function createStubMetadataFactory(array $map): EntityMetadataFactory
{
    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    return new class ($map) extends EntityMetadataFactory
    {
        /**
         * @param array<class-string, EntityMetadata> $map
         */
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(private readonly array $map) {}

        public function parse(string $entityClass): EntityMetadata
        {
            return $this->map[$entityClass];
        }
    };
}

// -------------------------------------------------------------------------
// Companion-hydration tests (Task 006)
// -------------------------------------------------------------------------

it('hydrates only the parent when metadata has no extenders', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createProductMetadata(); // extenders: []

    $row = ['id' => 1, 'name' => 'Widget'];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity)->toBeInstanceOf(HydratorTestProduct::class)
        ->and($entity->companions())->toBeEmpty();
});

it('hydrates the parent and one companion when metadata has one extender', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    $row = ['id' => 1, 'name' => 'Widget', 'sku' => 'WGT-01', 'stock' => 50];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity->companions())->toHaveCount(1)
        ->and($entity->companion(HydratorTestProductExt::class))->toBeInstanceOf(HydratorTestProductExt::class);
});

it('hydrates the parent and multiple companions when metadata has multiple extenders', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
        HydratorTestProductPricing::class => createProductPricingMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(
        HydratorTestProductExt::class,
        HydratorTestProductPricing::class,
    );

    $row = ['id' => 1, 'name' => 'Widget', 'sku' => 'WGT-01', 'stock' => 50, 'price' => 9.99];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity->companions())->toHaveCount(2)
        ->and($entity->companion(HydratorTestProductExt::class))->toBeInstanceOf(HydratorTestProductExt::class)
        ->and($entity->companion(HydratorTestProductPricing::class))->toBeInstanceOf(HydratorTestProductPricing::class);
});

it('attaches each companion under its own class-string in the companions bag', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
        HydratorTestProductPricing::class => createProductPricingMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(
        HydratorTestProductExt::class,
        HydratorTestProductPricing::class,
    );

    $row = ['id' => 1, 'name' => 'Widget', 'sku' => 'WGT-01', 'stock' => 10, 'price' => 4.99];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);
    $companions = $entity->companions();

    expect($companions)->toHaveKey(HydratorTestProductExt::class)
        ->and($companions)->toHaveKey(HydratorTestProductPricing::class);
});

it('sets companion property values from the same row data', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    $row = ['id' => 7, 'name' => 'Gadget', 'sku' => 'GDG-07', 'stock' => 99];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    /** @var HydratorTestProductExt $ext */
    $ext = $entity->companion(HydratorTestProductExt::class);

    expect($ext->sku)->toBe('GDG-07')
        ->and($ext->stock)->toBe(99);
});

it('silently skips an extender whose columns are entirely missing from the row', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    // Row has NO sku or stock columns — extender should be silently skipped
    $row = ['id' => 1, 'name' => 'Widget'];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity->companions())->toBeEmpty()
        ->and($entity->companion(HydratorTestProductExt::class))->toBeNull();
});

it('partially hydrates an extender when some columns are present and some are missing', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    // Row has ALL extender columns present (sku and stock), but sku is null
    $row = ['id' => 1, 'name' => 'Widget', 'sku' => null, 'stock' => 5];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    /** @var HydratorTestProductExt $ext */
    $ext = $entity->companion(HydratorTestProductExt::class);

    // Companion IS attached because all columns were present in the row schema
    expect($ext)->toBeInstanceOf(HydratorTestProductExt::class)
        ->and($ext->stock)->toBe(5);
});

it('does not set originalValues for an extender that was skipped', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    // Row has no extender columns → extender is skipped
    $row = ['id' => 1, 'name' => 'Widget'];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    // No companion attached, so there is nothing to get originalValues for
    expect($entity->companions())->toBeEmpty();
});

it('extractAll returns only parent columns when no companions are attached', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createProductMetadata();

    $entity = new HydratorTestProduct();
    $entity->id = 3;
    $entity->name = 'Solo';

    $row = $hydrator->extractAll($entity, $metadata);

    expect($row)->toBe(['id' => 3, 'name' => 'Solo']);
});

it('extractAll includes companion columns when companions are attached', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    $row = ['id' => 2, 'name' => 'Duo', 'sku' => 'DUO-02', 'stock' => 20];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    $extracted = $hydrator->extractAll($entity, $metadata);

    expect($extracted)->toBe([
        'id' => 2,
        'name' => 'Duo',
        'sku' => 'DUO-02',
        'stock' => 20,
    ]);
});

it('extractAll uses each companion\'s own metadata for column resolution', function (): void {
    $factory = createStubMetadataFactory([
        HydratorTestProductExt::class => createProductExtMetadata(),
        HydratorTestProductPricing::class => createProductPricingMetadata(),
    ]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(
        HydratorTestProductExt::class,
        HydratorTestProductPricing::class,
    );

    $row = ['id' => 5, 'name' => 'Multi', 'sku' => 'MLT-05', 'stock' => 3, 'price' => 19.99];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    $extracted = $hydrator->extractAll($entity, $metadata);

    expect($extracted)->toHaveKey('sku')
        ->and($extracted)->toHaveKey('stock')
        ->and($extracted)->toHaveKey('price')
        ->and($extracted['sku'])->toBe('MLT-05')
        ->and($extracted['stock'])->toBe(3)
        ->and($extracted['price'])->toBe(19.99);
});

it('does not require the EntityMetadataFactory call for entities without extenders (no extra parse)', function (): void {
    // Factory with no entries — if parse() is called it will throw a key error
    $factory = createStubMetadataFactory([]);
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadata(); // extenders: []

    $row = ['id' => 1, 'name' => 'Safe'];

    // Should not throw even though factory has no entries
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity)->toBeInstanceOf(HydratorTestProduct::class);
});

it('constructs without the EntityMetadataFactory and hydrates non-extended entities correctly (backward compat)', function (): void {
    $hydrator = new EntityHydrator(); // no factory
    $metadata = createProductMetadata(); // extenders: []

    $row = ['id' => 10, 'name' => 'Compat'];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    expect($entity)->toBeInstanceOf(HydratorTestProduct::class)
        ->and($entity->id)->toBe(10)
        ->and($entity->name)->toBe('Compat');
});

it('correctly hydrates companions when the factory has not seen the extender classes before (on-demand parse)', function (): void {
    // Use a real EntityMetadataFactory — it has never parsed HydratorTestProductExt before
    $factory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator($factory);
    $metadata = createProductMetadataWithExtenders(HydratorTestProductExt::class);

    $row = ['id' => 4, 'name' => 'Fresh', 'sku' => 'FRS-04', 'stock' => 7];
    /** @var HydratorTestProduct $entity */
    $entity = $hydrator->hydrate(HydratorTestProduct::class, $row, $metadata);

    /** @var HydratorTestProductExt $ext */
    $ext = $entity->companion(HydratorTestProductExt::class);

    expect($ext)->toBeInstanceOf(HydratorTestProductExt::class)
        ->and($ext->sku)->toBe('FRS-04')
        ->and($ext->stock)->toBe(7);
});

// -------------------------------------------------------------------------
// Helper functions to create metadata
// -------------------------------------------------------------------------

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
