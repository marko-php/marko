<?php

declare(strict_types=1);

use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\Exceptions\AmbiguousSortOrderException;
use Marko\Layout\Exceptions\ComponentNotFoundException;
use Marko\Layout\Exceptions\DuplicateComponentException;

function makeDefinition(
    string $className = 'App\Components\HeaderComponent',
    string $slot = 'header',
    int $sortOrder = 0,
    ?string $before = null,
    ?string $after = null,
): ComponentDefinition {
    return new ComponentDefinition(
        className: $className,
        template: 'component.phtml',
        slot: $slot,
        handles: ['default'],
        slots: [],
        sortOrder: $sortOrder,
        before: $before,
        after: $after,
    );
}

it('adds a component definition', function (): void {
    $collection = new ComponentCollection();
    $definition = makeDefinition('App\Components\HeaderComponent');

    $collection->add($definition);

    expect($collection->count())->toBe(1);
});

it('throws DuplicateComponentException when adding same class twice', function (): void {
    $collection = new ComponentCollection();
    $definition = makeDefinition('App\Components\HeaderComponent');

    $collection->add($definition);

    expect(fn () => $collection->add($definition))
        ->toThrow(DuplicateComponentException::class);
});

it('removes a component by class reference', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\HeaderComponent'));

    $collection->remove('App\Components\HeaderComponent');

    expect($collection->count())->toBe(0);
});

it('throws ComponentNotFoundException when removing non-existent component', function (): void {
    $collection = new ComponentCollection();

    expect(fn () => $collection->remove('App\Components\HeaderComponent'))
        ->toThrow(ComponentNotFoundException::class);
});

it('gets a component by class reference', function (): void {
    $collection = new ComponentCollection();
    $definition = makeDefinition('App\Components\HeaderComponent');
    $collection->add($definition);

    $result = $collection->get('App\Components\HeaderComponent');

    expect($result)->toBe($definition);
});

it('throws ComponentNotFoundException when getting non-existent component', function (): void {
    $collection = new ComponentCollection();

    expect(fn () => $collection->get('App\Components\HeaderComponent'))
        ->toThrow(ComponentNotFoundException::class);
});

it('returns all component definitions', function (): void {
    $collection = new ComponentCollection();
    $headerDef = makeDefinition('App\Components\HeaderComponent');
    $navDef = makeDefinition('App\Components\NavComponent');
    $collection->add($headerDef);
    $collection->add($navDef);

    $all = $collection->all();

    expect($all)->toBe([
        'App\Components\HeaderComponent' => $headerDef,
        'App\Components\NavComponent' => $navDef,
    ]);
});

it('returns components filtered by slot', function (): void {
    $collection = new ComponentCollection();
    $headerDef = makeDefinition('App\Components\HeaderComponent', 'header');
    $footerDef = makeDefinition('App\Components\FooterComponent', 'footer');
    $collection->add($headerDef);
    $collection->add($footerDef);

    $result = $collection->forSlot('header');

    expect($result)->toHaveCount(1)
        ->and($result[0])->toBe($headerDef);
});

it('sorts components by sortOrder within a slot', function (): void {
    $collection = new ComponentCollection();
    $first = makeDefinition('App\Components\FirstComponent', 'header', sortOrder: 10);
    $second = makeDefinition('App\Components\SecondComponent', 'header', sortOrder: 20);
    $collection->add($second);
    $collection->add($first);

    $result = $collection->forSlot('header');

    expect($result[0])->toBe($first)
        ->and($result[1])->toBe($second);
});

it('respects before constraint over sortOrder when sorting', function (): void {
    $collection = new ComponentCollection();
    $a = makeDefinition('App\Components\AComponent', 'header', sortOrder: 10);
    $b = makeDefinition('App\Components\BComponent', 'header', sortOrder: 20, before: 'App\Components\AComponent');
    $collection->add($a);
    $collection->add($b);

    $result = $collection->forSlot('header');

    expect($result[0]->className)->toBe('App\Components\BComponent')
        ->and($result[1]->className)->toBe('App\Components\AComponent');
});

it('respects after constraint over sortOrder when sorting', function (): void {
    $collection = new ComponentCollection();
    $a = makeDefinition('App\Components\AComponent', 'header', sortOrder: 10);
    $b = makeDefinition('App\Components\BComponent', 'header', sortOrder: 5, after: 'App\Components\AComponent');
    $collection->add($a);
    $collection->add($b);

    $result = $collection->forSlot('header');

    expect($result[0]->className)->toBe('App\Components\AComponent')
        ->and($result[1]->className)->toBe('App\Components\BComponent');
});

it('throws AmbiguousSortOrderException when two components have same sortOrder with no before or after constraints', function (): void {
    $collection = new ComponentCollection();
    $a = makeDefinition('App\Components\AComponent', 'header', sortOrder: 10);
    $b = makeDefinition('App\Components\BComponent', 'header', sortOrder: 10);
    $collection->add($a);
    $collection->add($b);

    expect(fn () => $collection->forSlot('header'))
        ->toThrow(AmbiguousSortOrderException::class);
});

it('moves a component to a different slot', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\HeaderComponent', 'header'));

    $collection->move('App\Components\HeaderComponent', 'footer');

    expect($collection->get('App\Components\HeaderComponent')->slot)->toBe('footer');
});

it('moves a component with a new sortOrder', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\HeaderComponent', 'header', sortOrder: 10));

    $collection->move('App\Components\HeaderComponent', 'footer', 99);

    $moved = $collection->get('App\Components\HeaderComponent');
    expect($moved->slot)->toBe('footer')
        ->and($moved->sortOrder)->toBe(99);
});

it('returns count of components', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\HeaderComponent'));
    $collection->add(makeDefinition('App\Components\NavComponent'));

    expect($collection->count())->toBe(2);
});

it('returns components grouped by slot', function (): void {
    $collection = new ComponentCollection();
    $header1 = makeDefinition('App\Components\HeaderComponent', 'header', sortOrder: 10);
    $header2 = makeDefinition('App\Components\NavComponent', 'header', sortOrder: 20);
    $footer = makeDefinition('App\Components\FooterComponent', 'footer', sortOrder: 10);
    $collection->add($header1);
    $collection->add($header2);
    $collection->add($footer);

    $grouped = $collection->groupedBySlot();

    expect($grouped)->toHaveKeys(['header', 'footer'])
        ->and($grouped['header'])->toHaveCount(2)
        ->and($grouped['footer'])->toHaveCount(1);
});
