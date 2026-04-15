<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;
use Marko\Layout\Tests\Unit\Helpers;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollectorInterface;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\Exceptions\CircularSlotException;
use Marko\Layout\Exceptions\SlotNotFoundException;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

// Fixtures

#[Layout(component: LpnFixtureRootComponent::class)]
class LpnFixtureController
{
    public function index(): void {}
}

#[Component(template: 'layouts/root.html', slots: ['content'])]
class LpnFixtureRootComponent {}

// A component that lives in "content" slot but defines sub-slots: tab.details and tab.reviews
#[Component(template: 'components/tabs.html', slot: 'content', handle: 'default', sortOrder: 10, slots: ['tab.details', 'tab.reviews'])]
class LpnFixtureTabsComponent {}

// Components targeting sub-slots
#[Component(template: 'components/tab-details.html', slot: 'tab.details', handle: 'default', sortOrder: 10)]
class LpnFixtureTabDetailsComponent {}

#[Component(template: 'components/tab-reviews.html', slot: 'tab.reviews', handle: 'default', sortOrder: 10)]
class LpnFixtureTabReviewsComponent {}

// A layout with two top-level slots
#[Layout(component: LpnFixtureTwoSlotRootComponent::class)]
class LpnFixtureTwoSlotController
{
    public function index(): void {}
}

#[Component(template: 'layouts/two-slot-root.html', slots: ['header', 'main'])]
class LpnFixtureTwoSlotRootComponent {}

#[Component(template: 'components/nav.html', slot: 'header', handle: 'default', sortOrder: 10)]
class LpnFixtureNavComponent {}

#[Component(template: 'components/body.html', slot: 'main', handle: 'default', sortOrder: 10, slots: ['body.sidebar', 'body.content'])]
class LpnFixtureBodyComponent {}

#[Component(template: 'components/sidebar.html', slot: 'body.sidebar', handle: 'default', sortOrder: 10)]
class LpnFixtureSidebarNestedComponent {}

#[Component(template: 'components/main-content.html', slot: 'body.content', handle: 'default', sortOrder: 10)]
class LpnFixtureMainContentComponent {}

// Deep nesting: tab.details itself defines sub-slots
#[Component(template: 'components/tabs-deep.html', slot: 'content', handle: 'default', sortOrder: 10, slots: ['tab.details'])]
class LpnFixtureDeepTabsComponent {}

#[Component(template: 'components/deep-details.html', slot: 'tab.details', handle: 'default', sortOrder: 10, slots: ['detail.images', 'detail.description'])]
class LpnFixtureDeepDetailsComponent {}

#[Component(template: 'components/detail-images.html', slot: 'detail.images', handle: 'default', sortOrder: 10)]
class LpnFixtureDetailImagesComponent {}

#[Component(template: 'components/detail-description.html', slot: 'detail.description', handle: 'default', sortOrder: 20)]
class LpnFixtureDetailDescriptionComponent {}

// Circular reference fixtures
// A is in content, A defines sub-slot: cycle.b
// B is in cycle.b, B defines sub-slot: content (pointing back to layout's top-level!)
// Actually: circular means slot graph has a cycle. Let's use:
// X defines sub-slot: cycle.y; Y defines sub-slot: cycle.x; X is in cycle.x; Y is in cycle.y
#[Component(template: 'components/cycle-x.html', slot: 'content', handle: 'default', sortOrder: 10, slots: ['cycle.y'])]
class LpnFixtureCycleXComponent {}

#[Component(template: 'components/cycle-y.html', slot: 'cycle.y', handle: 'default', sortOrder: 10, slots: ['cycle.x'])]
class LpnFixtureCycleYComponent {}

#[Component(template: 'components/cycle-filler.html', slot: 'cycle.x', handle: 'default', sortOrder: 10)]
class LpnFixtureCycleFillerComponent {}

// Helper stubs

function lpnStubCollector(ComponentCollection $collection): ComponentCollectorInterface
{
    return new class ($collection) implements ComponentCollectorInterface
    {
        public function __construct(private readonly ComponentCollection $stub) {}

        public function collect(array $classNames, string $handle): ComponentCollection
        {
            return $this->stub;
        }

        public function discoverFromClass(string $className): ?ComponentDefinition
        {
            return null;
        }
    };
}

function lpnStubView(callable $renderFn): ViewInterface
{
    return new class ($renderFn) implements ViewInterface
    {
        public function __construct(private readonly mixed $renderFn) {}

        public function render(string $template, array $data = []): Response
        {
            return Response::html(($this->renderFn)($template, $data));
        }

        public function renderToString(string $template, array $data = []): string
        {
            return ($this->renderFn)($template, $data);
        }
    };
}

function lpnBuildProcessor(ComponentCollection $collection, ViewInterface $view, string $controllerClass = LpnFixtureController::class): LayoutProcessor
{
    return new LayoutProcessor(
        container: Helpers::stubContainer(),
        layoutResolver: new LayoutResolver(),
        handleResolver: new HandleResolver(),
        componentCollector: lpnStubCollector($collection),
        componentDataResolver: new ComponentDataResolver(),
        view: $view,
    );
}

function lpnMakeCollection(array $definitions): ComponentCollection
{
    $collection = new ComponentCollection();

    foreach ($definitions as $def) {
        $collection->add($def);
    }

    return $collection;
}

describe('LayoutProcessor nested slots', function (): void {
    it('renders components into parent component sub-slots', function (): void {
        // tabs component in "content" renders with {slot tab.details}{/slot} and {slot tab.reviews}{/slot}
        // After tabs renders, its sub-slot content should be filled in

        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureTabsComponent::class,
                template: 'components/tabs.html',
                slot: 'content',
                handles: ['default'],
                slots: ['tab.details', 'tab.reviews'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureTabDetailsComponent::class,
                template: 'components/tab-details.html',
                slot: 'tab.details',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureTabReviewsComponent::class,
                template: 'components/tab-reviews.html',
                slot: 'tab.reviews',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
        ]);

        $slotsPassed = null;

        $view = lpnStubView(function (string $template, array $data) use (&$slotsPassed): string {
            if ($template === 'layouts/root.html') {
                $slotsPassed = $data['slots'] ?? null;
            }

            if ($template === 'components/tabs.html') {
                return '<tabs>{slot tab.details}{/slot}{slot tab.reviews}{/slot}</tabs>';
            }

            if ($template === 'components/tab-details.html') {
                return '<details/>';
            }

            if ($template === 'components/tab-reviews.html') {
                return '<reviews/>';
            }

            return '<div/>';
        });

        $processor = lpnBuildProcessor($collection, $view);
        $processor->process(LpnFixtureController::class, 'index', '/products', [], new Request());

        expect($slotsPassed)->toBeArray()
            ->and($slotsPassed['content'])->toContain('<details/>')
            ->and($slotsPassed['content'])->toContain('<reviews/>');
    });

    it('renders parent component before filling its sub-slots', function (): void {
        $renderOrder = [];

        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureTabsComponent::class,
                template: 'components/tabs.html',
                slot: 'content',
                handles: ['default'],
                slots: ['tab.details', 'tab.reviews'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureTabDetailsComponent::class,
                template: 'components/tab-details.html',
                slot: 'tab.details',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
        ]);

        $view = lpnStubView(function (string $template) use (&$renderOrder): string {
            $renderOrder[] = $template;

            if ($template === 'components/tabs.html') {
                return '<tabs>{slot tab.details}{/slot}</tabs>';
            }

            return '<div/>';
        });

        $processor = lpnBuildProcessor($collection, $view);
        $processor->process(LpnFixtureController::class, 'index', '/products', [], new Request());

        $tabsPos = array_search('components/tabs.html', $renderOrder, true);
        $detailsPos = array_search('components/tab-details.html', $renderOrder, true);

        expect($tabsPos)->not->toBeFalse()
            ->and($detailsPos)->not->toBeFalse()
            ->and($tabsPos)->toBeLessThan($detailsPos);
    });

    it('supports multiple levels of nesting', function (): void {
        // content -> tab.details -> detail.images + detail.description
        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureDeepTabsComponent::class,
                template: 'components/tabs-deep.html',
                slot: 'content',
                handles: ['default'],
                slots: ['tab.details'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureDeepDetailsComponent::class,
                template: 'components/deep-details.html',
                slot: 'tab.details',
                handles: ['default'],
                slots: ['detail.images', 'detail.description'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureDetailImagesComponent::class,
                template: 'components/detail-images.html',
                slot: 'detail.images',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureDetailDescriptionComponent::class,
                template: 'components/detail-description.html',
                slot: 'detail.description',
                handles: ['default'],
                slots: [],
                sortOrder: 20,
            ),
        ]);

        $slotsPassed = null;

        $view = lpnStubView(function (string $template, array $data) use (&$slotsPassed): string {
            if ($template === 'layouts/root.html') {
                $slotsPassed = $data['slots'] ?? null;
            }

            if ($template === 'components/tabs-deep.html') {
                return '<tabs>{slot tab.details}{/slot}</tabs>';
            }

            if ($template === 'components/deep-details.html') {
                return '<details>{slot detail.images}{/slot}{slot detail.description}{/slot}</details>';
            }

            if ($template === 'components/detail-images.html') {
                return '<images/>';
            }

            if ($template === 'components/detail-description.html') {
                return '<description/>';
            }

            return '<div/>';
        });

        $processor = lpnBuildProcessor($collection, $view);
        $processor->process(LpnFixtureController::class, 'index', '/products', [], new Request());

        expect($slotsPassed)->toBeArray()
            ->and($slotsPassed['content'])->toContain('<images/>')
            ->and($slotsPassed['content'])->toContain('<description/>');
    });

    it('throws SlotNotFoundException when targeting non-existent sub-slot', function (): void {
        // A component targets a sub-slot that no parent component has defined
        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureTabDetailsComponent::class,
                template: 'components/tab-details.html',
                slot: 'nonexistent.subslot',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
        ]);

        $view = lpnStubView(fn (string $t): string => '<div/>');

        $processor = lpnBuildProcessor($collection, $view);

        expect(fn () => $processor->process(
            LpnFixtureController::class,
            'index',
            '/products',
            [],
            new Request(),
        ))->toThrow(SlotNotFoundException::class);
    });

    it('detects circular slot references and throws an error', function (): void {
        // cycle.y is defined by CycleXComponent (in content slot)
        // cycle.x is defined by CycleYComponent (in cycle.y slot)
        // CycleFillerComponent is in cycle.x slot
        // This creates: content -> cycle.y -> cycle.x -> (back to content via cycle.x referencing content? No...)
        // Circular: X defines cycle.y, Y is in cycle.y and defines cycle.x, Filler is in cycle.x,
        // but cycle.x leads back to X's parent...
        // Actually for true circular: Y defines a slot that X also fills, creating cycle
        // Let's use: X in content defines sub-slot cycle.y; Y in cycle.y defines sub-slot cycle.x;
        // and we put X also in cycle.x — but X is already used. Use a different approach:
        // ComponentA defines sub-slot: slot.b
        // ComponentB is in slot.b, defines sub-slot: slot.a
        // ComponentA is also "in" slot.a... but same class can't be in two slots.
        // The circular chain is at the SLOT level: slot.b is owned by something in slot.a,
        // slot.a is owned by something in slot.b.
        // Our fixtures: CycleXComponent is in content, defines cycle.y
        //               CycleYComponent is in cycle.y, defines cycle.x
        //               CycleFillerComponent is in cycle.x... but cycle.x itself isn't a sub-slot
        // For a real cycle we need: something in cycle.x defines a sub-slot that is cycle.y (already defined by X)
        // Let's create that in this test directly:

        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureCycleXComponent::class,
                template: 'components/cycle-x.html',
                slot: 'content',
                handles: ['default'],
                slots: ['cycle.y'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureCycleYComponent::class,
                template: 'components/cycle-y.html',
                slot: 'cycle.y',
                handles: ['default'],
                slots: ['cycle.x'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureCycleFillerComponent::class,
                template: 'components/cycle-filler.html',
                slot: 'cycle.x',
                handles: ['default'],
                slots: ['cycle.y'],  // This creates the cycle: cycle.x -> cycle.y -> cycle.x
                sortOrder: 10,
            ),
        ]);

        $view = lpnStubView(fn (string $t): string => '<div/>');

        $processor = lpnBuildProcessor($collection, $view);

        expect(fn () => $processor->process(
            LpnFixtureController::class,
            'index',
            '/products',
            [],
            new Request(),
        ))->toThrow(CircularSlotException::class);
    });

    it('renders sub-slot components in sortOrder', function (): void {
        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureTabsComponent::class,
                template: 'components/tabs.html',
                slot: 'content',
                handles: ['default'],
                slots: ['tab.details', 'tab.reviews'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureTabDetailsComponent::class,
                template: 'components/tab-details.html',
                slot: 'tab.details',
                handles: ['default'],
                slots: [],
                sortOrder: 20,
            ),
            new ComponentDefinition(
                className: LpnFixtureTabReviewsComponent::class,
                template: 'components/tab-reviews.html',
                slot: 'tab.details',  // same sub-slot, lower sortOrder
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
        ]);

        $renderOrder = [];

        $view = lpnStubView(function (string $template) use (&$renderOrder): string {
            if ($template !== 'layouts/root.html' && $template !== 'components/tabs.html') {
                $renderOrder[] = $template;
            }

            if ($template === 'components/tabs.html') {
                return '<tabs>{slot tab.details}{/slot}</tabs>';
            }

            return '<div/>';
        });

        $processor = lpnBuildProcessor($collection, $view);
        $processor->process(LpnFixtureController::class, 'index', '/products', [], new Request());

        $reviewsPos = array_search('components/tab-reviews.html', $renderOrder, true);
        $detailsPos = array_search('components/tab-details.html', $renderOrder, true);

        // reviews (sortOrder 10) should render before details (sortOrder 20)
        expect($reviewsPos)->not->toBeFalse()
            ->and($detailsPos)->not->toBeFalse()
            ->and($reviewsPos)->toBeLessThan($detailsPos);
    });

    it('handles mix of top-level and nested slot components on same page', function (): void {
        // Layout has two top-level slots: header and main
        // header has a nav component (no sub-slots)
        // main has a body component (defines sub-slots: body.sidebar and body.content)

        $collection = lpnMakeCollection([
            new ComponentDefinition(
                className: LpnFixtureNavComponent::class,
                template: 'components/nav.html',
                slot: 'header',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureBodyComponent::class,
                template: 'components/body.html',
                slot: 'main',
                handles: ['default'],
                slots: ['body.sidebar', 'body.content'],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureSidebarNestedComponent::class,
                template: 'components/sidebar.html',
                slot: 'body.sidebar',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
            new ComponentDefinition(
                className: LpnFixtureMainContentComponent::class,
                template: 'components/main-content.html',
                slot: 'body.content',
                handles: ['default'],
                slots: [],
                sortOrder: 10,
            ),
        ]);

        $slotsPassed = null;

        $view = lpnStubView(function (string $template, array $data) use (&$slotsPassed): string {
            if ($template === 'layouts/two-slot-root.html') {
                $slotsPassed = $data['slots'] ?? null;
            }

            if ($template === 'components/body.html') {
                return '<body>{slot body.sidebar}{/slot}{slot body.content}{/slot}</body>';
            }

            if ($template === 'components/nav.html') {
                return '<nav/>';
            }

            if ($template === 'components/sidebar.html') {
                return '<sidebar/>';
            }

            if ($template === 'components/main-content.html') {
                return '<main-content/>';
            }

            return '<div/>';
        });

        $processor = lpnBuildProcessor($collection, $view, LpnFixtureTwoSlotController::class);
        $processor->process(LpnFixtureTwoSlotController::class, 'index', '/products', [], new Request());

        expect($slotsPassed)->toBeArray()
            ->and($slotsPassed['header'])->toBe('<nav/>')
            ->and($slotsPassed['main'])->toContain('<sidebar/>')
            ->and($slotsPassed['main'])->toContain('<main-content/>');
    });
});
