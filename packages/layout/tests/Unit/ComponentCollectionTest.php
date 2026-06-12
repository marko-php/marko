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

it(
    'throws AmbiguousSortOrderException when two components have same sortOrder with no before or after constraints',
    function (): void {
        $collection = new ComponentCollection();
        $a = makeDefinition('App\Components\AComponent', 'header', sortOrder: 10);
        $b = makeDefinition('App\Components\BComponent', 'header', sortOrder: 10);
        $collection->add($a);
        $collection->add($b);

        expect(fn () => $collection->forSlot('header'))
            ->toThrow(AmbiguousSortOrderException::class);
    },
);

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

it('detects an ambiguous sort-order pair among 17 or more components', function (): void {
    $collection = new ComponentCollection();

    // Add 15 components with unique sort orders (10, 20, ..., 150)
    for ($i = 1; $i <= 15; $i++) {
        $collection->add(makeDefinition("App\\Components\\C{$i}Component", 'main', sortOrder: $i * 10));
    }

    // Add 2 more components with the same sort order 999 and no before/after — ambiguous pair
    $collection->add(makeDefinition('App\Components\AmbiguousAComponent', 'main', sortOrder: 999));
    $collection->add(makeDefinition('App\Components\AmbiguousBComponent', 'main', sortOrder: 999));

    // 17 total components; without deterministic pre-scan usort skips the ambiguous pair
    expect(fn () => $collection->forSlot('main'))
        ->toThrow(AmbiguousSortOrderException::class);
});

it('still applies before/after constraints after the ambiguity check', function (): void {
    $collection = new ComponentCollection();
    // A: sortOrder 20, B: sortOrder 10 but must come after A
    $a = makeDefinition('App\Components\AComponent', 'widget', sortOrder: 20);
    $b = makeDefinition('App\Components\BComponent', 'widget', sortOrder: 10, after: 'App\Components\AComponent');
    $collection->add($a);
    $collection->add($b);

    $result = $collection->forSlot('widget');

    // B has after constraint: A then B, despite B having lower sortOrder
    expect($result[0]->className)->toBe('App\Components\AComponent')
        ->and($result[1]->className)->toBe('App\Components\BComponent');
});

it('sorts resolved components by sort order deterministically', function (): void {
    $collection = new ComponentCollection();
    // Components with same sortOrder; B and C have before/after so only A is unresolved
    // Expected order: B (sortOrder 10, before A), A (sortOrder 10), C (sortOrder 10, after A)
    $a = makeDefinition('App\Components\AComponent', 'footer', sortOrder: 10);
    $b = makeDefinition('App\Components\BComponent', 'footer', sortOrder: 10, before: 'App\Components\AComponent');
    $c = makeDefinition('App\Components\CComponent', 'footer', sortOrder: 20);
    $collection->add($c);
    $collection->add($a);
    $collection->add($b);

    $result = $collection->forSlot('footer');

    expect($result[0]->className)->toBe('App\Components\BComponent')
        ->and($result[1]->className)->toBe('App\Components\AComponent')
        ->and($result[2]->className)->toBe('App\Components\CComponent');
});

it('does not throw when all sort orders are unique', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\AComponent', 'nav', sortOrder: 10));
    $collection->add(makeDefinition('App\Components\BComponent', 'nav', sortOrder: 20));
    $collection->add(makeDefinition('App\Components\CComponent', 'nav', sortOrder: 30));

    $result = $collection->forSlot('nav');

    expect($result)->toHaveCount(3);
});

it(
    'does not throw when components share a sort order but all but one are resolved by before/after',
    function (): void {
        $collection = new ComponentCollection();
        // A and B share sortOrder 10; B has a before constraint, so only A is unresolved
        $collection->add(makeDefinition('App\Components\AComponent', 'sidebar', sortOrder: 10));
        $collection->add(
            makeDefinition('App\Components\BComponent', 'sidebar', sortOrder: 10, before: 'App\Components\AComponent'),
        );

        // Should not throw: only one unresolved component at sortOrder 10
        $result = $collection->forSlot('sidebar');

        expect($result)->toHaveCount(2);
    },
);

it('throws AmbiguousSortOrderException naming the conflicting components', function (): void {
    $collection = new ComponentCollection();
    $collection->add(makeDefinition('App\Components\AComponent', 'content', sortOrder: 50));
    $collection->add(makeDefinition('App\Components\BComponent', 'content', sortOrder: 50));

    $exception = null;

    try {
        $collection->forSlot('content');
    } catch (AmbiguousSortOrderException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->getContext())->toContain('App\Components\AComponent')
        ->and($exception->getContext())->toContain('App\Components\BComponent');
});
