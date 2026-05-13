<?php

declare(strict_types=1);

use Marko\Scope\Attributes\Scoped;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;
use Marko\Scope\Metadata\ScopeMetadata;
use Marko\Scope\Metadata\ScopeMetadataFactory;
use Marko\Scope\Registry\ScopeRegistryInterface;

class EntityWithNoScopedProperties
{
    public string $name = '';

    public int $price = 0;
}

class EntityWithScopedProperties
{
    #[Scoped(axes: ['store', 'website'])]
    public string $name = '';

    #[Scoped(axes: ['website'])]
    public string $description = '';

    public int $price = 0;
}

class EntityWithUnknownAxis
{
    #[Scoped(axes: ['unknown_axis'])]
    public string $title = '';
}

function makeMockRegistry(array $knownAxes): ScopeRegistryInterface
{
    return new readonly class ($knownAxes) implements ScopeRegistryInterface
    {
        public function __construct(private readonly array $knownAxes) {}

        public function hasAxis(string $name): bool
        {
            return in_array($name, $this->knownAxes, true);
        }

        public function getAxis(string $name): ScopeAxis
        {
            throw new RuntimeException('Not implemented');
        }

        public function listAxes(): array
        {
            return $this->knownAxes;
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            throw new RuntimeException('Not implemented');
        }
    };
}

it('returns empty metadata for entity classes with no Scoped properties', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $metadata = $factory->for(EntityWithNoScopedProperties::class);

    expect($metadata)->toBeInstanceOf(ScopeMetadata::class)
        ->and($metadata->hasScopedProperties())->toBeFalse();
});

it('discovers Scoped properties on an entity class via reflection', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->hasScopedProperties())->toBeTrue();
});

it('returns declared axes for a scoped property in declaration order', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->axesForProperty('name'))->toBe(['store', 'website'])
        ->and($metadata->axesForProperty('description'))->toBe(['website']);
});

it('caches metadata per class within the factory', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $first = $factory->for(EntityWithScopedProperties::class);
    $second = $factory->for(EntityWithScopedProperties::class);

    expect($first)->toBe($second);
});

it('throws UnknownAxisException when a Scoped property declares an unknown axis', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    expect(fn () => $factory->for(EntityWithUnknownAxis::class))
        ->toThrow(UnknownAxisException::class);
});

it('reports whether a property is scoped via isScoped', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->isScoped('name'))->toBeTrue()
        ->and($metadata->isScoped('price'))->toBeFalse();
});

it('lists all scoped property names via scopedProperties', function (): void {
    $registry = makeMockRegistry(['store', 'website']);
    $factory = new ScopeMetadataFactory($registry);

    $metadata = $factory->for(EntityWithScopedProperties::class);

    expect($metadata->scopedProperties())->toBe(['name', 'description']);
});
