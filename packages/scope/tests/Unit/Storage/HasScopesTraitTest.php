<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Scope\Storage\HasScopes;
use Marko\Scope\Storage\HasScopesInterface;

// ─── Fixture ─────────────────────────────────────────────────────────────────

#[Table(name: 'trait_products')]
class TraitProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('exposes the HasScopesInterface override methods via the trait', function (): void {
    $entity = new TraitProduct();

    expect(method_exists($entity, 'setOverride'))->toBeTrue()
        ->and(method_exists($entity, 'getOverride'))->toBeTrue()
        ->and(method_exists($entity, 'hasOverride'))->toBeTrue()
        ->and(method_exists($entity, 'clearOverride'))->toBeTrue()
        ->and(method_exists($entity, 'allOverrides'))->toBeTrue();
});

it('stores multiple overrides keyed by scopeKey and property', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('locale:de', 'name', 'Hallo');
    $entity->setOverride('geo:eu.de', 'price', 19.99);

    expect($entity->allOverrides())->toBe([
        'geo:eu.de' => ['name' => 'Hemd', 'price' => 19.99],
        'locale:de' => ['name' => 'Hallo'],
    ]);
});

it('returns null for unknown scopeKey or property via getOverride', function (): void {
    $entity = new TraitProduct();

    expect($entity->getOverride('geo:eu.de', 'name'))->toBeNull()
        ->and($entity->getOverride('unknown', 'price'))->toBeNull();
});

it('returns false for hasOverride when no override exists', function (): void {
    $entity = new TraitProduct();

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeFalse();
});

it('distinguishes an explicit null override from no override via hasOverride', function (): void {
    $entity = new TraitProduct();

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeFalse();

    $entity->setOverride('geo:eu.de', 'name', null);

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeTrue()
        ->and($entity->getOverride('geo:eu.de', 'name'))->toBeNull();
});

it('clears a single property override leaving others intact', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('geo:eu.de', 'price', 19.99);
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->getOverride('geo:eu.de', 'name'))->toBeNull()
        ->and($entity->getOverride('geo:eu.de', 'price'))->toBe(19.99);
});

it('sets scopes to null when the last override is cleared', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->scopes)->toBeNull();
});

it(
    'declares a json nullable Column attribute on the scopes property when reflected via a consuming class',
    function (): void {
        $reflection = new ReflectionClass(TraitProduct::class);

        expect($reflection->hasProperty('scopes'))->toBeTrue();

        $property = $reflection->getProperty('scopes');
        $attributes = $property->getAttributes(Column::class);

        expect($attributes)->toHaveCount(1);

        $column = $attributes[0]->newInstance();

        expect($column->name)->toBe('scopes')
            ->and($column->type)->toBe('json')
            ->and($column->nullable)->toBeTrue();
    },
);

it('a class using HasScopes can satisfy the HasScopesInterface contract', function (): void {
    $entity = new TraitProduct();

    expect($entity)->toBeInstanceOf(HasScopesInterface::class);
});

it('HasScopesInterface declares the override methods', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('setOverride'))->toBeTrue()
        ->and($reflection->hasMethod('getOverride'))->toBeTrue()
        ->and($reflection->hasMethod('hasOverride'))->toBeTrue()
        ->and($reflection->hasMethod('clearOverride'))->toBeTrue()
        ->and($reflection->hasMethod('allOverrides'))->toBeTrue();
});
