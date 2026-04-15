<?php

declare(strict_types=1);

use Marko\Layout\ComponentDefinition;

it('stores component class name', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\HeaderComponent',
        template: 'header.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->className)->toBe('App\Components\HeaderComponent');
});

it('stores template from attribute', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\HeaderComponent',
        template: 'header.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->template)->toBe('header.phtml');
});

it('stores slot from attribute', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\NavComponent',
        template: 'nav.phtml',
        slot: 'header.nav',
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->slot)->toBe('header.nav');
});

it('stores resolved handle strings', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\HeaderComponent',
        template: 'header.phtml',
        slot: null,
        handles: ['default', 'catalog_product_view'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->handles)->toBe(['default', 'catalog_product_view']);
});

it('stores slots array for nested composition', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\TabsComponent',
        template: 'tabs.phtml',
        slot: null,
        handles: ['default'],
        slots: ['tab.details', 'tab.reviews'],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->slots)->toBe(['tab.details', 'tab.reviews']);
});

it('stores sortOrder defaulting to 0', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\HeaderComponent',
        template: 'header.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($definition->sortOrder)->toBe(0);
});

it('stores before and after class references', function (): void {
    $definition = new ComponentDefinition(
        className: 'App\Components\NavComponent',
        template: 'nav.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: 'App\Components\HeaderComponent',
        after: 'App\Components\FooterComponent',
    );

    expect($definition->before)->toBe('App\Components\HeaderComponent')
        ->and($definition->after)->toBe('App\Components\FooterComponent');
});

it('can determine if it has a data method via reflection', function (): void {
    $classWithData = new class ()
    {
        public function data(): array
        {
            return [];
        }
    };

    $classWithoutData = new class () {};

    $withData = new ComponentDefinition(
        className: $classWithData::class,
        template: 'component.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    $withoutData = new ComponentDefinition(
        className: $classWithoutData::class,
        template: 'component.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($withData->hasDataMethod())->toBeTrue()
        ->and($withoutData->hasDataMethod())->toBeFalse();
});

it('can determine if it defines sub-slots', function (): void {
    $withSlots = new ComponentDefinition(
        className: 'App\Components\TabsComponent',
        template: 'tabs.phtml',
        slot: null,
        handles: ['default'],
        slots: ['tab.details', 'tab.reviews'],
        sortOrder: 0,
        before: null,
        after: null,
    );

    $withoutSlots = new ComponentDefinition(
        className: 'App\Components\HeaderComponent',
        template: 'header.phtml',
        slot: null,
        handles: ['default'],
        slots: [],
        sortOrder: 0,
        before: null,
        after: null,
    );

    expect($withSlots->hasSubSlots())->toBeTrue()
        ->and($withoutSlots->hasSubSlots())->toBeFalse();
});
