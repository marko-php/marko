<?php

declare(strict_types=1);

/*
 * Renders the same page through marko/layout with each view driver's real
 * template engine, so a layout and its nested sub-slots behave the same in
 * Latte and Twig.
 */

use Latte\Engine;
use Latte\Loaders\StringLoader;
use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\ComponentCollection;
use Marko\Layout\ComponentCollectorInterface;
use Marko\Layout\ComponentDataResolver;
use Marko\Layout\ComponentDefinition;
use Marko\Layout\HandleResolver;
use Marko\Layout\LayoutProcessor;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Tests\Unit\Helpers;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\Latte\Extensions\SlotExtension as LatteSlotExtension;
use Marko\View\Twig\SlotExtension as TwigSlotExtension;
use Marko\View\ViewInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[Layout(component: LepFixtureRootLayout::class)]
class LepFixtureController
{
    public function index(): void {}
}

#[Component(template: 'layouts/root', slots: ['header', 'content'])]
class LepFixtureRootLayout {}

class LepFixtureNav
{
    /**
     * @return array<string, string>
     */
    public function data(): array
    {
        return ['title' => 'Home & Co'];
    }
}

class LepFixtureTabs {}

class LepFixtureDetails {}

class LepFixtureReviews {}

function lepCollector(): ComponentCollectorInterface
{
    $collection = new ComponentCollection();

    foreach ([
        new ComponentDefinition(LepFixtureNav::class, 'components/nav', 'header', ['default'], [], 10),
        new ComponentDefinition(
            LepFixtureTabs::class,
            'components/tabs',
            'content',
            ['default'],
            ['tab.details', 'tab.reviews'],
            10,
        ),
        new ComponentDefinition(LepFixtureDetails::class, 'components/details', 'tab.details', ['default'], [], 10),
        new ComponentDefinition(LepFixtureReviews::class, 'components/reviews', 'tab.reviews', ['default'], [], 10),
    ] as $definition) {
        $collection->add($definition);
    }

    return new readonly class ($collection) implements ComponentCollectorInterface
    {
        public function __construct(private ComponentCollection $collection) {}

        public function collect(
            array $classNames,
            string $handle,
        ): ComponentCollection {
            return $this->collection;
        }

        public function discoverFromClass(string $className): ?ComponentDefinition
        {
            return null;
        }
    };
}

/**
 * @param Closure(string, array<string, mixed>): string $render
 */
function lepView(Closure $render): ViewInterface
{
    return new readonly class ($render) implements ViewInterface
    {
        public function __construct(private Closure $renderTemplate) {}

        public function render(
            string $template,
            array $data = [],
        ): Response {
            return Response::html($this->renderToString($template, $data));
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return ($this->renderTemplate)($template, $data);
        }
    };
}

function lepRenderPage(ViewInterface $view): string
{
    $processor = new LayoutProcessor(
        container: Helpers::stubContainer(),
        layoutResolver: new LayoutResolver(),
        handleResolver: new HandleResolver(),
        componentCollector: lepCollector(),
        componentDataResolver: new ComponentDataResolver(),
        view: $view,
    );

    return $processor->process(LepFixtureController::class, 'index', '/', [], new Request())->body();
}

const LEP_EXPECTED_PAGE = '<body><header><nav>Home &amp; Co</nav></header>'
    . '<main><tabs><p>Details</p><p>Reviews</p></tabs></main></body>';

it('renders a layout with nested sub-slots through Latte', function (): void {
    $latte = new Engine();
    $latte->setLoader(new StringLoader([
        'layouts/root' => '<body><header>{slot header/}</header><main>{slot content/}</main></body>',
        'components/nav' => '<nav>{$title}</nav>',
        'components/tabs' => '<tabs>{slot tab.details/}{slot tab.reviews/}</tabs>',
        'components/details' => '<p>Details</p>',
        'components/reviews' => '<p>Reviews</p>',
    ]));
    $latte->addExtension(new LatteSlotExtension());

    $html = lepRenderPage(lepView(
        fn (string $template, array $data): string => $latte->renderToString($template, $data),
    ));

    expect($html)->toBe(LEP_EXPECTED_PAGE);
});

it('renders the same layout with nested sub-slots through Twig', function (): void {
    $twig = new Environment(new ArrayLoader([
        'layouts/root' => '<body><header>{{ slot(\'header\') }}</header><main>{{ slot(\'content\') }}</main></body>',
        'components/nav' => '<nav>{{ title }}</nav>',
        'components/tabs' => '<tabs>{{ slot(\'tab.details\') }}{{ slot(\'tab.reviews\') }}</tabs>',
        'components/details' => '<p>Details</p>',
        'components/reviews' => '<p>Reviews</p>',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addExtension(new TwigSlotExtension());

    $html = lepRenderPage(lepView(
        fn (string $template, array $data): string => $twig->render($template, $data),
    ));

    expect($html)->toBe(LEP_EXPECTED_PAGE);
});
