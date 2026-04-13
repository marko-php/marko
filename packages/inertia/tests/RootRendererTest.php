<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\SsrGatewayInterface;
use Marko\Inertia\Rendering\RootRenderer;
use Marko\Inertia\Ssr\SsrPage;
use Marko\Vite\Contracts\ViteManagerInterface;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

function makeRootRenderer(array $config = [], ?SsrPage $ssrPage = null): RootRenderer
{
    $inertiaConfig = new InertiaConfig(new ConfigRepository([
        'inertia' => array_replace_recursive([
            'root' => [
                'id' => 'app',
                'title' => 'Marko',
            ],
            'ssr' => [
                'enabled' => false,
                'url' => 'http://127.0.0.1:13714',
                'bundle' => null,
                'ensure_bundle_exists' => false,
                'throw_on_error' => false,
            ],
        ], $config),
    ]));

    $vite = new class () implements ViteManagerInterface
    {
        public function isDevelopment(): bool
        {
            return false;
        }

        public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection
        {
            throw new RuntimeException('Not needed for test');
        }

        public function tags(string|array|null $entrypoints = null): string
        {
            return '<script src="/build/app.js"></script>';
        }

        public function scripts(string|array|null $entrypoints = null): string
        {
            return '';
        }

        public function styles(string|array|null $entrypoints = null): string
        {
            return '';
        }
    };

    $ssr = new class ($ssrPage) implements SsrGatewayInterface
    {
        public function __construct(
            private readonly ?SsrPage $page,
        ) {}

        public function render(array $page): ?SsrPage
        {
            return $this->page;
        }
    };

    return new RootRenderer($inertiaConfig, $vite, $ssr);
}

it('renders the client bootstrap root when ssr does not provide markup', function (): void {
    $renderer = makeRootRenderer();

    $html = $renderer->render([
        'component' => 'Home',
        'props' => ['title' => 'Home'],
        'url' => '/',
        'version' => null,
    ]);

    expect($html)->toContain('<script data-page="app" type="application/json">')
        ->and($html)->toContain('<div id="app"></div>')
        ->and($html)->toContain('<script src="/build/app.js"></script>');
});

it('renders ssr markup and head content when available', function (): void {
    $renderer = makeRootRenderer(
        config: [
            'ssr' => [
                'enabled' => true,
            ],
        ],
        ssrPage: new SsrPage(
            body: '<div id="app">SSR content</div>',
            head: ['<meta name="description" content="SSR">'],
        ),
    );

    $html = $renderer->render([
        'component' => 'Home',
        'props' => [],
        'url' => '/',
        'version' => null,
    ]);

    expect($html)->toContain('<div id="app">SSR content</div>')
        ->and($html)->toContain('<meta name="description" content="SSR">')
        ->and($html)->not->toContain('data-page=');
});
