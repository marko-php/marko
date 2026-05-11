<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityExtension;
use Marko\Database\Entity\EntityExtensionMetadataFactory;
use Marko\Database\Entity\EntityExtensionRegistry;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\ExtensionMetadata;
use Marko\Database\Exceptions\EntityException;

// ── Fixture classes ────────────────────────────────────────────────────────────

#[Table('ext_merge_users')]
class ExtMergeUser extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $name;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergeExtensionA extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extraA;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergeExtensionB extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extraB;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergeColumnConflictExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(name: 'name')]
    public string $conflictingColumn;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergeExtExtColumnConflictA extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(name: 'shared_col')]
    public string $propA;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergeExtExtColumnConflictB extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(name: 'shared_col')]
    public string $propB;
}

#[ExtensionOf(entityClass: ExtMergeUser::class)]
class ExtMergePropertyConflictExtension extends EntityExtension
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $name;
}

// ── Tests ──────────────────────────────────────────────────────────────────────

it('includes an empty extensions map by default on EntityMetadata', function (): void {
    $metadata = new EntityMetadata(
        entityClass: ExtMergeUser::class,
        tableName: 'ext_merge_users',
        primaryKey: 'id',
    );

    expect($metadata->extensions)->toBeEmpty();
});

it('constructs EntityMetadataFactory with null registry and factory for backward compatibility', function (): void {
    $factory = new EntityMetadataFactory();

    expect($factory)->toBeInstanceOf(EntityMetadataFactory::class);
});

it('populates extensions from the registry when parsing an entity with registered extensions', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register(ExtMergeUser::class, ExtMergeExtensionA::class);
    $registry->register(ExtMergeUser::class, ExtMergeExtensionB::class);

    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $metadata = $factory->parse(ExtMergeUser::class);

    expect($metadata->extensions)
        ->toHaveCount(2)
        ->and($metadata->extensions)->toHaveKey(ExtMergeExtensionA::class)
        ->and($metadata->extensions)->toHaveKey(ExtMergeExtensionB::class)
        ->and($metadata->extensions[ExtMergeExtensionA::class])->toBeInstanceOf(ExtensionMetadata::class)
        ->and($metadata->extensions[ExtMergeExtensionB::class])->toBeInstanceOf(ExtensionMetadata::class);
});

it('leaves extensions empty when no extensions are registered for the entity', function (): void {
    $registry = new EntityExtensionRegistry();
    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $metadata = $factory->parse(ExtMergeUser::class);

    expect($metadata->extensions)->toBeEmpty();
});

it('leaves extensions empty when constructed without a registry', function (): void {
    $factory = new EntityMetadataFactory();

    $metadata = $factory->parse(ExtMergeUser::class);

    expect($metadata->extensions)->toBeEmpty();
});

it('throws when an extension column name collides with a base entity column name', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register(ExtMergeUser::class, ExtMergeColumnConflictExtension::class);

    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $factory->parse(ExtMergeUser::class);
})->throws(EntityException::class);

it('throws when two extension column names collide with each other', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register(ExtMergeUser::class, ExtMergeExtExtColumnConflictA::class);
    $registry->register(ExtMergeUser::class, ExtMergeExtExtColumnConflictB::class);

    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $factory->parse(ExtMergeUser::class);
})->throws(EntityException::class);

it('throws when an extension property name collides with a base entity property name', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register(ExtMergeUser::class, ExtMergePropertyConflictExtension::class);

    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $factory->parse(ExtMergeUser::class);
})->throws(EntityException::class);

it('caches entity metadata including extensions', function (): void {
    $registry = new EntityExtensionRegistry();
    $registry->register(ExtMergeUser::class, ExtMergeExtensionA::class);

    $extensionMetadataFactory = new EntityExtensionMetadataFactory();
    $factory = new EntityMetadataFactory($registry, $extensionMetadataFactory);

    $metadata1 = $factory->parse(ExtMergeUser::class);
    $metadata2 = $factory->parse(ExtMergeUser::class);

    expect($metadata1)->toBe($metadata2);
});
