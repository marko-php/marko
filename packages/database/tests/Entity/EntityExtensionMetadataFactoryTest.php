<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityExtension;
use Marko\Database\Entity\EntityExtensionMetadataFactory;
use Marko\Database\Entity\ExtensionMetadata;
use Marko\Database\Exceptions\EntityException;

// ── Fixture classes ────────────────────────────────────────────────────────────

enum ExtensionTestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

#[Table('ext_users')]
class ExtFactoryUser extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryBasicExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extraField;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryMultiColExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $intCol;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $strCol;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public float $floatCol;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public bool $boolCol;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryCamelCaseExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $firstName;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $userID;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $HTMLContent;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryExplicitNameExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(name: 'custom_name')]
    public string $myProperty;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryNullableExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $required;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?string $optional;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryDefaultsExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $status = 'active';

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public int $count = 0;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?string $noDefault;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryEnumExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ExtensionTestStatus $status = ExtensionTestStatus::Active;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryNoColumnsExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public string $notAColumn;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryPrimaryKeyExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryRelationshipExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[HasOne(entityClass: ExtFactoryUser::class, foreignKey: 'user_id')]
    public ?ExtFactoryUser $related = null;
}

class ExtFactoryNoExtensionOfExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}

class ExtFactoryNotExtendingEntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryJsonMismatchExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(type: 'json')]
    public string $badJsonProp;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryCacheExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}

#[ExtensionOf(entityClass: ExtFactoryUser::class)]
class ExtFactoryBelongsToExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[BelongsTo(entityClass: ExtFactoryUser::class, foreignKey: 'user_id')]
    public ?ExtFactoryUser $owner = null;
}

// ── Tests ──────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->factory = new EntityExtensionMetadataFactory();
});

it('parses public Column-annotated properties into ExtensionMetadata', function (): void {
    $metadata = $this->factory->parse(ExtFactoryBasicExtension::class);

    expect($metadata)
        ->toBeInstanceOf(ExtensionMetadata::class)
        ->and($metadata->properties)->toHaveCount(1)
        ->and($metadata->columns)->toHaveCount(1)
        ->and($metadata->columns[0]->name)->toBe('extra_field');
});

it('stores the extension class and entity class on ExtensionMetadata', function (): void {
    $metadata = $this->factory->parse(ExtFactoryBasicExtension::class);

    expect($metadata->extensionClass)
        ->toBe(ExtFactoryBasicExtension::class)
        ->and($metadata->entityClass)->toBe(ExtFactoryUser::class);
});

it('throws when extension class has no Column-annotated properties', function (): void {
    $this->factory->parse(ExtFactoryNoColumnsExtension::class);
})->throws(EntityException::class, 'at least one #[Column]');

it('throws when extension class declares a primary key column', function (): void {
    $this->factory->parse(ExtFactoryPrimaryKeyExtension::class);
})->throws(EntityException::class, 'must not declare a primary key');

it('throws when extension class declares a relationship attribute', function (): void {
    $this->factory->parse(ExtFactoryRelationshipExtension::class);
})->throws(EntityException::class, 'must not declare relationship');

it('throws when extension class is missing the ExtensionOf attribute', function (): void {
    $this->factory->parse(ExtFactoryNoExtensionOfExtension::class);
})->throws(EntityException::class, 'missing #[ExtensionOf]');

it('infers database types from PHP scalar types', function (): void {
    $metadata = $this->factory->parse(ExtFactoryMultiColExtension::class);

    expect($metadata->columns[0]->type)
        ->toBe('integer')
        ->and($metadata->columns[1]->type)->toBe('varchar')
        ->and($metadata->columns[2]->type)->toBe('decimal')
        ->and($metadata->columns[3]->type)->toBe('boolean');
});

it('converts camelCase property names to snake_case column names', function (): void {
    $metadata = $this->factory->parse(ExtFactoryCamelCaseExtension::class);

    expect($metadata->columns[0]->name)
        ->toBe('first_name')
        ->and($metadata->columns[1]->name)->toBe('user_id')
        ->and($metadata->columns[2]->name)->toBe('html_content');
});

it('uses explicit column name from Column attribute when provided', function (): void {
    $metadata = $this->factory->parse(ExtFactoryExplicitNameExtension::class);

    expect($metadata->columns[0]->name)->toBe('custom_name');
});

it('correctly marks nullable properties', function (): void {
    $metadata = $this->factory->parse(ExtFactoryNullableExtension::class);

    expect($metadata->columns[0]->nullable)
        ->toBeFalse()
        ->and($metadata->columns[1]->nullable)->toBeTrue();
});

it('captures declared default values', function (): void {
    $metadata = $this->factory->parse(ExtFactoryDefaultsExtension::class);

    expect($metadata->columns[0]->default)
        ->toBe('active')
        ->and($metadata->columns[1]->default)->toBe(0)
        ->and($metadata->columns[2]->default)->toBeNull();
});

it('converts BackedEnum default values to their backing value', function (): void {
    $metadata = $this->factory->parse(ExtFactoryEnumExtension::class);

    expect($metadata->columns[0]->default)->toBe('active');
});

it('throws when extension class does not extend EntityExtension', function (): void {
    $this->factory->parse(ExtFactoryNotExtendingEntityExtension::class);
})->throws(EntityException::class, 'must extend EntityExtension');

it('throws when a json column type does not match the PHP array type', function (): void {
    $this->factory->parse(ExtFactoryJsonMismatchExtension::class);
})->throws(EntityException::class, "type: 'json'");

it('caches parsed metadata by class', function (): void {
    $metadata1 = $this->factory->parse(ExtFactoryCacheExtension::class);
    $metadata2 = $this->factory->parse(ExtFactoryCacheExtension::class);

    expect($metadata1)->toBe($metadata2);
});
