<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Entity\Entity;
use Marko\Scope\Storage\HasScopesInterface;
use Marko\Scope\Storage\ScopedOverridesEntity;

// Concrete subclass used for all unit tests
class ConcreteOverrides extends ScopedOverridesEntity {}

// Simple parent entity for companion tests
class ConcreteParentEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    public ?int $id = null;
}

it('is an abstract Entity subclass with a Scoped column named scopes typed as json', function (): void {
    $reflection = new ReflectionClass(ScopedOverridesEntity::class);

    expect($reflection->isAbstract())->toBeTrue()
        ->and($reflection->getParentClass()->getName())->toBe(Entity::class)
        ->and($reflection->hasProperty('scopes'))->toBeTrue();

    $property = $reflection->getProperty('scopes');
    $attributes = $property->getAttributes(Column::class);

    expect($attributes)->toHaveCount(1);

    $column = $attributes[0]->newInstance();

    expect($column->name)->toBe('scopes')
        ->and($column->type)->toBe('json')
        ->and($column->nullable)->toBeTrue();
});

it('stores an override keyed by scope key and property via setOverride', function (): void {
    $entity = new ConcreteOverrides();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');

    expect($entity->scopes)->toBe([
        'geo:eu.de' => ['name' => 'Hemd'],
    ]);
});

it('returns the stored override via getOverride for the same property and scope', function (): void {
    $entity = new ConcreteOverrides();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');

    expect($entity->getOverride('geo:eu.de', 'name'))->toBe('Hemd');
});

it('returns null from getOverride when no override exists at that scope', function (): void {
    $entity = new ConcreteOverrides();

    expect($entity->getOverride('geo:eu.de', 'name'))->toBeNull();
});

it('removes an override via clearOverride and getOverride returns null afterward', function (): void {
    $entity = new ConcreteOverrides();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('geo:eu.de', 'price', 19.99);
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->getOverride('geo:eu.de', 'name'))->toBeNull()
        ->and($entity->getOverride('geo:eu.de', 'price'))->toBe(19.99);
});

it('removes the entire scope-key sub-map when its last property is cleared', function (): void {
    $entity = new ConcreteOverrides();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->scopes)->toBeNull();
});

it(
    'functions as a companion attached via Entity::attachCompanion and is retrievable via Entity::companion',
    function (): void {
        $parent = new ConcreteParentEntity();
        $overrides = new ConcreteOverrides();
        $overrides->setOverride('geo:eu.de', 'name', 'Hemd');

        $parent->attachCompanion($overrides);

        $retrieved = $parent->companion(ConcreteOverrides::class);

        expect($retrieved)->toBeInstanceOf(ConcreteOverrides::class)
            ->and($retrieved)->toBe($overrides)
            ->and($retrieved->getOverride('geo:eu.de', 'name'))->toBe('Hemd');
    },
);

it('lists all overrides via allOverrides as the flat scope-key-first map', function (): void {
    $entity = new ConcreteOverrides();
    $entity->setOverride('locale:de', 'name', 'Hallo');
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('geo:eu.de', 'price', 19.99);

    expect($entity->allOverrides())->toBe([
        'geo:eu.de'  => ['name' => 'Hemd', 'price' => 19.99],
        'locale:de'  => ['name' => 'Hallo'],
    ]);
});

it(
    'ScopedOverridesEntity implements HasScopesInterface and its public method signatures match the interface',
    function (): void {
        $entity = new ConcreteOverrides();

        expect($entity)->toBeInstanceOf(HasScopesInterface::class);

        $reflection = new ReflectionClass(ScopedOverridesEntity::class);
        $interface = new ReflectionClass(HasScopesInterface::class);

        foreach ($interface->getMethods() as $interfaceMethod) {
            expect($reflection->hasMethod($interfaceMethod->getName()))->toBeTrue();
        }
    },
);

it('distinguishes an explicit null override from no override via hasOverride', function (): void {
    $entity = new ConcreteOverrides();

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeFalse();

    $entity->setOverride('geo:eu.de', 'name', null);

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeTrue()
        ->and($entity->getOverride('geo:eu.de', 'name'))->toBeNull();
});
