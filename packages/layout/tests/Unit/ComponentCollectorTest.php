<?php

declare(strict_types=1);

namespace Marko\Layout\Tests\Unit;

use Marko\Layout\Attributes\Component;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollector;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\HandleResolver;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;

// Test fixtures defined inline
#[Component(template: 'product/show.phtml', slot: 'content', handle: 'catalog_product_show')]
class ProductShowComponent {}

#[Component(template: 'home/hero.phtml', slot: 'content', handle: 'default')]
class DefaultHandleComponent {}

#[Component(template: 'product/list.phtml', slot: 'sidebar', handle: 'catalog_*')]
class CatalogPrefixComponent {}

#[Component(template: 'other/page.phtml', slot: 'content', handle: 'checkout_cart')]
class OtherPageComponent {}

#[Component(template: 'multi/component.phtml', slot: 'content', handle: ['catalog_product_show', 'catalog_category_view'])]
class MultiHandleComponent {}

#[Component(template: 'root/layout.phtml', slots: ['content', 'sidebar'], handle: 'default')]
class RootLayoutComponent {}

it('discovers component attributes from a class', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $definition = $collector->discoverFromClass(ProductShowComponent::class);

    expect($definition)->not->toBeNull();
});

it('creates ComponentDefinition from discovered attributes', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $definition = $collector->discoverFromClass(ProductShowComponent::class);

    expect($definition)->toBeInstanceOf(ComponentDefinition::class)
        ->and($definition->className)->toBe(ProductShowComponent::class)
        ->and($definition->template)->toBe('product/show.phtml')
        ->and($definition->slot)->toBe('content')
        ->and($definition->handles)->toBe(['catalog_product_show']);
});

it('collects components matching an exact handle', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $collection = $collector->collect([ProductShowComponent::class], 'catalog_product_show');

    expect($collection->count())->toBe(1)
        ->and($collection->get(ProductShowComponent::class))->toBeInstanceOf(ComponentDefinition::class);
});

it('collects components matching a handle prefix', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $collection = $collector->collect([CatalogPrefixComponent::class], 'catalog_product_show');

    expect($collection->count())->toBe(1)
        ->and($collection->get(CatalogPrefixComponent::class))->toBeInstanceOf(ComponentDefinition::class);
});

it('always includes components with default handle', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $collection = $collector->collect([DefaultHandleComponent::class], 'catalog_product_show');

    expect($collection->count())->toBe(1)
        ->and($collection->get(DefaultHandleComponent::class))->toBeInstanceOf(ComponentDefinition::class);
});

it('excludes components that do not match the handle', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $collection = $collector->collect([OtherPageComponent::class], 'catalog_product_show');

    expect($collection->count())->toBe(0);
});

it('resolves class-reference handles to string handles', function (): void {
    $routeCollection = new RouteCollection();
    $routeCollection->add(new RouteDefinition(
        method: 'GET',
        path: '/products',
        controller: ProductShowComponent::class,
        action: 'show',
    ));

    #[Component(template: 'product/show.phtml', slot: 'content', handle: [ProductShowComponent::class, 'show'])]
    class ClassRefHandleComponent {}

    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: $routeCollection,
    );

    $definition = $collector->discoverFromClass(ClassRefHandleComponent::class);

    expect($definition)->not->toBeNull()
        ->and($definition->handles)->toBe(['products_productshowcomponent_show']);
});

it('returns a ComponentCollection with all matching components', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $classes = [
        ProductShowComponent::class,
        DefaultHandleComponent::class,
        OtherPageComponent::class,
        CatalogPrefixComponent::class,
    ];

    $collection = $collector->collect($classes, 'catalog_product_show');

    expect($collection)->toBeInstanceOf(ComponentCollection::class)
        ->and($collection->count())->toBe(3);
});

it('skips classes with attributes from uninstalled packages gracefully', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $result = $collector->discoverFromClass('Marko\NonExistentPackage\SomeClass');

    expect($result)->toBeNull();
});

it('discovers components targeting multiple handles', function (): void {
    $collector = new ComponentCollector(
        handleResolver: new HandleResolver(),
        routeCollection: new RouteCollection(),
    );

    $definition = $collector->discoverFromClass(MultiHandleComponent::class);

    expect($definition)->not->toBeNull()
        ->and($definition->handles)->toBe(['catalog_product_show', 'catalog_category_view']);
});
