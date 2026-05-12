<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;

// Test fixtures
class ParentEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $id;
}

class CompanionEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $id;
}

class AnotherCompanionEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public int $id;
}

it('creates Entity base class that can be extended', function (): void {
    $entity = new class () extends Entity
    {
        public int $id;

        public string $name;
    };

    $entity->id = 1;
    $entity->name = 'Test';

    expect($entity)
        ->toBeInstanceOf(Entity::class)
        ->and($entity->id)->toBe(1)
        ->and($entity->name)->toBe('Test');
});

it('returns empty companions array for a fresh entity', function (): void {
    $entity = new ParentEntity();

    expect($entity->companions())->toBeEmpty();
});

it('returns null from companion when class is not attached', function (): void {
    $entity = new ParentEntity();

    expect($entity->companion(CompanionEntity::class))->toBeNull();
});

it('attaches a companion via hydrator and exposes it through companions array', function (): void {
    $hydrator = new EntityHydrator();
    $parent = new ParentEntity();
    $companion = new CompanionEntity();

    $hydrator->attachCompanion($parent, $companion);

    expect($parent->companions())
        ->toHaveKey(CompanionEntity::class)
        ->and($parent->companions()[CompanionEntity::class])->toBe($companion);
});

it('attaches a companion via Entity::attachCompanion and exposes it through companions array', function (): void {
    $parent = new ParentEntity();
    $companion = new CompanionEntity();

    $parent->attachCompanion($companion);

    expect($parent->companions())
        ->toHaveKey(CompanionEntity::class)
        ->and($parent->companions()[CompanionEntity::class])->toBe($companion);
});

it('returns the same companion instance from companion(class) lookup', function (): void {
    $parent = new ParentEntity();
    $companion = new CompanionEntity();

    $parent->attachCompanion($companion);

    expect($parent->companion(CompanionEntity::class))->toBe($companion);
});

it('returns the typed companion instance with correct class via companion lookup', function (): void {
    $parent = new ParentEntity();
    $companion = new CompanionEntity();

    $parent->attachCompanion($companion);

    /** @var CompanionEntity $found */
    $found = $parent->companion(CompanionEntity::class);

    expect($found)
        ->toBeInstanceOf(CompanionEntity::class)
        ->toBe($companion);
});

it('keeps companion bags isolated between two entity instances', function (): void {
    $parent1 = new ParentEntity();
    $parent2 = new ParentEntity();
    $companion1 = new CompanionEntity();
    $companion2 = new CompanionEntity();

    $parent1->attachCompanion($companion1);
    $parent2->attachCompanion($companion2);

    expect($parent1->companion(CompanionEntity::class))->toBe($companion1)
        ->and($parent2->companion(CompanionEntity::class))->toBe($companion2)
        ->and($parent1->companion(CompanionEntity::class))->not->toBe($companion2);
});

it('allows multiple companions of different classes on the same entity', function (): void {
    $parent = new ParentEntity();
    $companion = new CompanionEntity();
    $another = new AnotherCompanionEntity();

    $parent->attachCompanion($companion);
    $parent->attachCompanion($another);

    expect($parent->companions())->toHaveCount(2)
        ->and($parent->companion(CompanionEntity::class))->toBe($companion)
        ->and($parent->companion(AnotherCompanionEntity::class))->toBe($another);
});

it('overwrites an existing companion of the same class when reattached', function (): void {
    $parent = new ParentEntity();
    $first = new CompanionEntity();
    $second = new CompanionEntity();

    $parent->attachCompanion($first);
    $parent->attachCompanion($second);

    expect($parent->companions())->toHaveCount(1)
        ->and($parent->companion(CompanionEntity::class))->toBe($second);
});

it('shares storage between the public Entity::attachCompanion and the internal hydrator attach (one WeakMap, not two)', function (): void {
    $hydrator = new EntityHydrator();
    $parent = new ParentEntity();
    $companionViaEntity = new CompanionEntity();
    $companionViaHydrator = new AnotherCompanionEntity();

    // Attach one via Entity public API, one via hydrator internal API
    $parent->attachCompanion($companionViaEntity);
    $hydrator->attachCompanion($parent, $companionViaHydrator);

    // Both are visible through the same Entity::companions() call
    expect($parent->companions())->toHaveCount(2)
        ->and($parent->companion(CompanionEntity::class))->toBe($companionViaEntity)
        ->and($parent->companion(AnotherCompanionEntity::class))->toBe($companionViaHydrator);
});
