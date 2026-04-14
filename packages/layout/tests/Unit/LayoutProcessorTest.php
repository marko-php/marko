<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;
use Marko\Layout\Tests\Unit\Helpers;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollectorInterface;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\Exceptions\SlotNotFoundException;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

// Fixtures

#[Layout(component: LpFixtureRootComponent::class)]
class LpFixtureController
{
    public function index(): void {}
}

#[Component(template: 'layouts/root.html', slots: ['content', 'sidebar'])]
class LpFixtureRootComponent {}

#[Component(template: 'components/content.html', slot: 'content', handle: 'default', sortOrder: 10)]
class LpFixtureContentComponent
{
    public function data(): array
    {
        return ['title' => 'Hello'];
    }
}

#[Component(template: 'components/sidebar.html', slot: 'sidebar', handle: 'default', sortOrder: 5)]
class LpFixtureSidebarComponent {}

#[Component(template: 'components/widget.html', slot: 'undefined_slot', handle: 'default', sortOrder: 10)]
class LpFixtureInvalidSlotComponent {}

#[Component(template: 'components/param.html', slot: 'content', handle: 'default', sortOrder: 10)]
class LpFixtureParamComponent
{
    public function data(string $id): array
    {
        return ['id' => $id];
    }
}

// Helper to build a ComponentCollection from fixture classes matching a handle
function buildCollection(array $classNames, string $handle, HandleResolver $handleResolver): ComponentCollection
{
    $collection = new ComponentCollection();

    foreach ($classNames as $className) {
        $reflection = new ReflectionClass($className);
        $attrs = $reflection->getAttributes(Component::class);

        if ($attrs === []) {
            continue;
        }

        $attr = $attrs[0]->newInstance();
        $handles = is_string($attr->handle) ? [$attr->handle] : $attr->handle;
        $matches = array_any($handles, fn (string $h): bool => $handleResolver->matches($h, $handle));

        if ($matches) {
            $collection->add(new ComponentDefinition(
                className: $className,
                template: $attr->template,
                slot: $attr->slot,
                handles: $handles,
                slots: $attr->slots,
                sortOrder: $attr->sortOrder,
                before: $attr->before,
                after: $attr->after,
            ));
        }
    }

    return $collection;
}

// Stub ComponentCollector that returns a pre-built collection
function stubCollector(ComponentCollection $collection): ComponentCollectorInterface
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

// Stub ViewInterface with a callable for renderToString
function stubView(callable $renderFn): ViewInterface
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

function defaultView(): ViewInterface
{
    return stubView(fn (string $t, array $d): string => $t === 'layouts/root.html' ? '<html/>' : '<div/>');
}

function defaultCollection(): ComponentCollection
{
    $handleResolver = new HandleResolver();
    $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

    return buildCollection(
        [LpFixtureContentComponent::class, LpFixtureSidebarComponent::class],
        $handle,
        $handleResolver,
    );
}

function buildProcessor(ComponentCollection $collection, ViewInterface $view): LayoutProcessor
{
    return new LayoutProcessor(
        container: Helpers::stubContainer(),
        layoutResolver: new LayoutResolver(),
        handleResolver: new HandleResolver(),
        componentCollector: stubCollector($collection),
        componentDataResolver: new ComponentDataResolver(),
        view: $view,
    );
}

describe('LayoutProcessor', function (): void {
    it('resolves layout from controller and method', function (): void {
        $processor = buildProcessor(defaultCollection(), defaultView());
        $response = $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('generates handle from route definition', function (): void {
        $handleResolver = new HandleResolver();
        $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

        // No namespace → strrpos returns false → substr offset is 1, strips first char
        expect($handle)->toBe('products_pfixture_index');
    });

    it('collects components for the generated handle', function (): void {
        $handleResolver = new HandleResolver();
        $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

        $collection = buildCollection(
            [LpFixtureContentComponent::class, LpFixtureSidebarComponent::class],
            $handle,
            $handleResolver,
        );

        expect($collection->count())->toBe(2);
    });

    it('resolves data for each component', function (): void {
        $dataResolver = new ComponentDataResolver();
        $component = new LpFixtureContentComponent();

        $result = $dataResolver->resolve($component, [], new Request());

        expect($result)->toBe(['title' => 'Hello']);
    });

    it('renders each component template with its data via ViewInterface', function (): void {
        $rendered = [];

        $view = stubView(function (string $template, array $data) use (&$rendered): string {
            $rendered[$template] = $data;
            return '<div/>';
        });

        $processor = buildProcessor(defaultCollection(), $view);
        $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        expect(array_key_exists('components/content.html', $rendered))->toBeTrue()
            ->and(array_key_exists('components/sidebar.html', $rendered))->toBeTrue()
            ->and($rendered['components/content.html'])->toBe(['title' => 'Hello']);
    });

    it('assembles rendered HTML into slot buckets', function (): void {
        $slotsPassed = null;

        $view = stubView(function (string $template, array $data) use (&$slotsPassed): string {
            if ($template === 'layouts/root.html') {
                $slotsPassed = $data['slots'] ?? null;
            }
            return '<div/>';
        });

        $processor = buildProcessor(defaultCollection(), $view);
        $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        expect($slotsPassed)->toBeArray()
            ->and(array_key_exists('content', $slotsPassed))->toBeTrue()
            ->and(array_key_exists('sidebar', $slotsPassed))->toBeTrue();
    });

    it('renders the layout template with assembled slots', function (): void {
        $layoutTemplateCalled = false;

        $view = stubView(function (string $template) use (&$layoutTemplateCalled): string {
            if ($template === 'layouts/root.html') {
                $layoutTemplateCalled = true;
            }
            return '<div/>';
        });

        $processor = buildProcessor(defaultCollection(), $view);
        $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        expect($layoutTemplateCalled)->toBeTrue();
    });

    it('returns an HTML Response with the final output', function (): void {
        $view = stubView(fn (string $t): string => $t === 'layouts/root.html' ? '<html>final</html>' : '<div/>');

        $processor = buildProcessor(defaultCollection(), $view);
        $response = $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->body())->toBe('<html>final</html>')
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');
    });

    it('renders components in sortOrder within each slot', function (): void {
        $handleResolver = new HandleResolver();
        $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

        // Two components in same slot: sortOrder 20 and 10 — lower sortOrder renders first
        $collection = new ComponentCollection();
        $collection->add(new ComponentDefinition(
            className: LpFixtureContentComponent::class,
            template: 'components/content.html',
            slot: 'content',
            handles: ['products_lpfixture_index'],
            slots: [],
            sortOrder: 20,
        ));

        // Add a second component with lower sortOrder in same slot using sidebar as content
        $second = new ComponentDefinition(
            className: LpFixtureSidebarComponent::class,
            template: 'components/sidebar.html',
            slot: 'content',
            handles: ['products_lpfixture_index'],
            slots: [],
            sortOrder: 10,
        );
        $collection->add($second);

        $renderOrder = [];

        $view = stubView(function (string $template) use (&$renderOrder): string {
            if ($template !== 'layouts/root.html') {
                $renderOrder[] = $template;
            }
            return '<div/>';
        });

        $processor = buildProcessor($collection, $view);
        $processor->process(LpFixtureController::class, 'index', '/products', [], new Request());

        // sidebar (sortOrder 10) should appear before content (sortOrder 20) in the render order
        $sidebarPos = array_search('components/sidebar.html', $renderOrder, true);
        $contentPos = array_search('components/content.html', $renderOrder, true);

        expect($sidebarPos)->toBeLessThan($contentPos);
    });

    it('passes route parameters to component data resolution', function (): void {
        $handleResolver = new HandleResolver();
        $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

        $collection = buildCollection(
            [LpFixtureParamComponent::class],
            $handle,
            $handleResolver,
        );

        $renderedData = [];

        $view = stubView(function (string $template, array $data) use (&$renderedData): string {
            $renderedData[$template] = $data;
            return '<div/>';
        });

        $processor = buildProcessor($collection, $view);
        $processor->process(LpFixtureController::class, 'index', '/products', ['id' => '42'], new Request());

        expect($renderedData['components/param.html'])->toBe(['id' => '42']);
    });

    it('throws SlotNotFoundException when component targets undefined slot', function (): void {
        $handleResolver = new HandleResolver();
        $handle = $handleResolver->generate('/products', LpFixtureController::class, 'index');

        $collection = buildCollection(
            [LpFixtureInvalidSlotComponent::class],
            $handle,
            $handleResolver,
        );

        $processor = buildProcessor($collection, defaultView());

        expect(fn () => $processor->process(
            LpFixtureController::class,
            'index',
            '/products',
            [],
            new Request(),
        ))->toThrow(SlotNotFoundException::class);
    });
});
