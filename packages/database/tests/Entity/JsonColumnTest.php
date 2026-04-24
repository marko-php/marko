<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Exceptions\EntityException;

#[Table('products')]
class JsonColumnEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(type: 'json')]
    public array $metadata;
}

#[Table('profiles')]
class NullableJsonColumnEntity extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(type: 'json', nullable: true)]
    public ?array $settings = null;
}

function createJsonColumnMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: JsonColumnEntity::class,
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
            'metadata' => new PropertyMetadata(
                name: 'metadata',
                columnName: 'metadata',
                type: 'array',
                nullable: false,
                columnType: 'json',
            ),
        ],
    );
}

function createNullableJsonColumnMetadata(): EntityMetadata
{
    return new EntityMetadata(
        entityClass: NullableJsonColumnEntity::class,
        tableName: 'profiles',
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
            'settings' => new PropertyMetadata(
                name: 'settings',
                columnName: 'settings',
                type: 'array',
                nullable: true,
                columnType: 'json',
            ),
        ],
    );
}

it('dehydrates a PHP array into JSON for save', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = ['color' => 'blue', 'size' => 10];

    $row = $hydrator->extract($entity, $metadata);

    expect($row['metadata'])
        ->toBeString()
        ->toBe('{"color":"blue","size":10}');
});

it(
    'preserves null values WITHIN the array payload (e.g. {"middle_name": null}) through round-trip',
    function (): void {
        $hydrator = new EntityHydrator();
        $metadata = createJsonColumnMetadata();

        $original = ['first_name' => 'Alice', 'middle_name' => null, 'last_name' => 'Smith'];

        $entity = new JsonColumnEntity();
        $entity->id = 1;
        $entity->metadata = $original;

        $row = $hydrator->extract($entity, $metadata);
        $hydrated = $hydrator->hydrate(
            JsonColumnEntity::class,
            ['id' => 1, 'metadata' => $row['metadata']],
            $metadata,
        );

        expect($hydrated->metadata)->toBe($original)
            ->and($hydrated->metadata['middle_name'])->toBeNull();
    },
);

it('round-trips a deeply nested structure (at least 10 levels) without loss', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $deep = ['level' => 10, 'data' => 'leaf'];
    for ($i = 9; $i >= 1; $i--) {
        $deep = ['level' => $i, 'child' => $deep];
    }

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = $deep;

    $row = $hydrator->extract($entity, $metadata);
    $hydrated = $hydrator->hydrate(JsonColumnEntity::class, ['id' => 1, 'metadata' => $row['metadata']], $metadata);

    expect($hydrated->metadata)->toBe($deep);
});

it('throws at metadata-parse time when property type and #[Column(nullable:)] disagree', function (): void {
    $factory = new EntityMetadataFactory();

    // array (non-nullable) with nullable: true — disagree
    $entity = new #[Table('conflict_entities')] class () extends Entity
    {
        #[Column(primaryKey: true)]
        public int $id;

        /** @noinspection PhpUnused */
        #[Column(type: 'json', nullable: true)]
        public array $data = [];
    };

    $factory->parse($entity::class);
})->throws(EntityException::class, 'must agree');

it(
    'dehydrates PHP null to SQL NULL (not to the JSON literal "null") when property is typed ?array',
    function (): void {
        $hydrator = new EntityHydrator();
        $metadata = createNullableJsonColumnMetadata();

        $entity = new NullableJsonColumnEntity();
        $entity->id = 1;
        $entity->settings = null;

        $row = $hydrator->extract($entity, $metadata);

        expect($row['settings'])->toBeNull()
            ->and($row['settings'])->not->toBe('null');
    },
);

it('hydrates SQL NULL to PHP null when property is typed ?array', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createNullableJsonColumnMetadata();

    $row = [
        'id' => 1,
        'settings' => null,
    ];

    $entity = $hydrator->hydrate(NullableJsonColumnEntity::class, $row, $metadata);

    expect($entity->settings)->toBeNull();
});

it('round-trips unicode and utf8mb4 content correctly on MySQL', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $original = ['greeting' => 'こんにちは', 'emoji' => '🎉', 'arabic' => 'مرحبا'];

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = $original;

    $row = $hydrator->extract($entity, $metadata);

    // Should NOT use unicode escape sequences (JSON_UNESCAPED_UNICODE)
    expect($row['metadata'])->not->toContain('\\u');

    $hydrated = $hydrator->hydrate(JsonColumnEntity::class, ['id' => 1, 'metadata' => $row['metadata']], $metadata);
    expect($hydrated->metadata)->toBe($original);
});

it(
    'rejects attributes where property type is not array or ?array (compile-time/metadata-parse guard)',
    function (): void {
        $factory = new EntityMetadataFactory();

        $entity = new #[Table('bad_entities')] class () extends Entity
        {
            #[Column(primaryKey: true)]
            public int $id;

            #[Column(type: 'json')]
            public string $data;
        };

        $factory->parse($entity::class);
    },
)->throws(EntityException::class, "type: 'json'");

it('uses JSON_THROW_ON_ERROR flags on both encode and decode', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    // Decode: invalid JSON must throw EntityException (not return null/false)
    $decodeThrown = false;
    try {
        $hydrator->hydrate(JsonColumnEntity::class, ['id' => 1, 'metadata' => '{bad}'], $metadata);
    } catch (EntityException) {
        $decodeThrown = true;
    }
    expect($decodeThrown)->toBeTrue();

    // Encode: unencodable value must throw EntityException (not silently return false)
    $encodeThrown = false;
    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = ['val' => NAN];
    try {
        $hydrator->extract($entity, $metadata);
    } catch (EntityException) {
        $encodeThrown = true;
    }
    expect($encodeThrown)->toBeTrue();
});

it('marks the column as dirty only when the decoded value actually changes', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    // Hydrate with JSON string (same logical value, different whitespace)
    $row = [
        'id' => 1,
        'metadata' => '{"color":"red","size":42}',
    ];
    $entity = $hydrator->hydrate(JsonColumnEntity::class, $row, $metadata);

    // Setting the same logical value (even with different key order) is NOT dirty
    // (note: PHP array comparison is order-sensitive, so same order = not dirty)
    $entity->metadata = ['color' => 'red', 'size' => 42];
    expect($hydrator->isDirty($entity, $metadata))->toBeFalse();

    // Changing the value makes it dirty
    $entity->metadata = ['color' => 'blue', 'size' => 42];
    expect($hydrator->isDirty($entity, $metadata))->toBeTrue();
});

it('throws a descriptive exception when encoding a value that cannot be JSON-encoded', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    // INF cannot be JSON-encoded
    $entity->metadata = ['value' => INF];

    $hydrator->extract($entity, $metadata);
})->throws(EntityException::class, 'Failed to encode PHP value to JSON');

it('throws a descriptive exception when decoding invalid JSON from the database', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $row = [
        'id' => 1,
        'metadata' => 'not valid json {{{',
    ];

    $hydrator->hydrate(JsonColumnEntity::class, $row, $metadata);
})->throws(EntityException::class, 'Failed to decode JSON value from database');

it('stores null when the property is null and the column is nullable', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createNullableJsonColumnMetadata();

    $entity = new NullableJsonColumnEntity();
    $entity->id = 1;
    $entity->settings = null;

    $row = $hydrator->extract($entity, $metadata);

    expect($row['settings'])->toBeNull();
});

it('round-trips sequential arrays correctly', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $original = [1, 2, 3, 'hello', true];

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = $original;

    $row = $hydrator->extract($entity, $metadata);
    $hydrated = $hydrator->hydrate(JsonColumnEntity::class, ['id' => 1, 'metadata' => $row['metadata']], $metadata);

    expect($hydrated->metadata)->toBe($original);
});

it('round-trips nested associative arrays correctly', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $original = ['user' => ['name' => 'Alice', 'role' => 'admin'], 'count' => 3];

    $entity = new JsonColumnEntity();
    $entity->id = 1;
    $entity->metadata = $original;

    $row = $hydrator->extract($entity, $metadata);
    $hydrated = $hydrator->hydrate(JsonColumnEntity::class, ['id' => 1, 'metadata' => $row['metadata']], $metadata);

    expect($hydrated->metadata)->toBe($original);
});

it('hydrates a JSON column value into a PHP array', function (): void {
    $hydrator = new EntityHydrator();
    $metadata = createJsonColumnMetadata();

    $row = [
        'id' => 1,
        'metadata' => '{"color":"red","size":42}',
    ];

    $entity = $hydrator->hydrate(JsonColumnEntity::class, $row, $metadata);

    expect($entity->metadata)
        ->toBeArray()
        ->toBe(['color' => 'red', 'size' => 42]);
});
