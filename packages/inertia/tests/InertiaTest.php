<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
use Marko\Inertia\Exceptions\InertiaConfigurationException;
use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeSession;
use Marko\Vite\Vite;

beforeEach(function () {
    $this->basePath = dirname(__DIR__);
    $this->paths = new ProjectPaths($this->basePath);
});

function createInertia(array $config = [], array $viteConfig = []): Inertia
{
    $mergedConfig = new FakeConfigRepository(array_merge([
        'inertia.version' => '1.0',
        'inertia.assetEntry' => null,
        'inertia.ssr.enabled' => false,
        'inertia.ssr.url' => 'http://localhost:13714',
        'vite.entry' => 'app/web/resources/js/app.js',
        'vite.buildDirectory' => 'build',
        'vite.manifestFilename' => '.vite/manifest.json',
        'vite.devServerUrl' => 'http://localhost:5173',
        'vite.devServerStylesheets' => [],
        'vite.useDevServer' => true,
    ], $config, array_combine(
        array_map(static fn (string $key): string => "vite.$key", array_keys($viteConfig)),
        array_values($viteConfig),
    ) ?: []));

    $paths = new ProjectPaths(dirname(__DIR__));
    $vite = new Vite($mergedConfig, $paths);
    $ssrClient = new SsrClient($mergedConfig, new NullSsrTransport());

    return new Inertia($mergedConfig, $vite, $ssrClient, new FakeSession());
}

test('inertia returns json for inertia requests', function () {
    $inertia = createInertia();
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($response->headers()['Vary'])->toBe('X-Inertia');

    $data = json_decode($response->body(), true);
    expect($data['component'])->toBe('Dashboard')
        ->and($data['props'])->toHaveKey('errors')
        ->and($data['props']['user']['name'])->toBe('Test');
});

test('inertia returns html for non-inertia requests', function () {
    $inertia = createInertia();
    $request = new Request();

    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
        ->and($response->headers()['Vary'])->toBe('X-Inertia')
        ->and($response->body())->toContain('<!DOCTYPE html>')
        ->toContain('<script data-page="app" type="application/json">')
        ->toContain('data-page=');
});

test('inertia html can target a custom vite asset entry', function () {
    $inertia = createInertia(viteConfig: [
        'devServerUrl' => 'http://localhost:5173',
        'devServerStylesheets' => [],
        'useDevServer' => true,
    ]);
    $request = new Request();

    $response = $inertia->render(
        request: $request,
        component: 'ReactHome',
        assetEntry: 'app/react-web/resources/js/app.jsx',
    );

    expect($response->body())->toContain('http://localhost:5173/app/react-web/resources/js/app.jsx')
        ->not->toContain('app/web/resources/js/app.js');
});

test('inertia html defaults to the configured vite entry', function () {
    $inertia = createInertia(viteConfig: [
        'entry' => 'app/admin/resources/js/admin.js',
        'devServerUrl' => 'http://localhost:5173',
        'devServerStylesheets' => [],
        'useDevServer' => true,
    ]);
    $request = new Request();

    $response = $inertia->render($request, 'AdminHome');

    expect($response->body())->toContain('http://localhost:5173/app/admin/resources/js/admin.js')
        ->not->toContain('app/web/resources/js/app.js');
});

test('inertia html defaults to the configured inertia asset entry when present', function () {
    $inertia = createInertia([
        'inertia.assetEntry' => 'app/react-web/resources/js/app.jsx',
    ], [
        'entry' => 'app/web/resources/js/app.js',
        'devServerUrl' => 'http://localhost:5173',
        'devServerStylesheets' => [],
        'useDevServer' => true,
    ]);
    $request = new Request();

    $response = $inertia->render($request, 'ReactHome');

    expect($response->body())->toContain('http://localhost:5173/app/react-web/resources/js/app.jsx')
        ->not->toContain('app/web/resources/js/app.js');
});

test('inertia merges shared data with page props', function () {
    $inertia = createInertia();
    $inertia->share('flash', ['message' => 'Hello']);

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    $data = json_decode($response->body(), true);
    expect($data['props']['flash']['message'])->toBe('Hello')
        ->and($data['props']['user']['name'])->toBe('Test');
});

test('inertia location redirect returns x-inertia-location header', function () {
    $inertia = createInertia();
    $response = $inertia->location('https://example.com');

    expect($response->statusCode())->toBe(409)
        ->and($response->headers()['Vary'])->toBe('X-Inertia')
        ->and($response->headers()['X-Inertia-Location'])->toBe('https://example.com');
});

test('inertia resolves lazy props on full load', function () {
    $inertia = createInertia();
    $called = false;

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $response = $inertia->render($request, 'Dashboard', [
        'user' => ['name' => 'Test'],
        'expensive' => function () use (&$called) {
            $called = true;

            return ['data' => 'loaded'];
        },
    ]);

    expect($called)->toBeTrue();

    $data = json_decode($response->body(), true);
    expect($data['props']['expensive']['data'])->toBe('loaded');
});

test('inertia skips lazy props on partial reload when not requested', function () {
    $inertia = createInertia();
    $called = false;

    $request = new Request(server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'user',
    ]);

    $response = $inertia->render($request, 'Dashboard', [
        'user' => ['name' => 'Test'],
        'expensive' => function () use (&$called) {
            $called = true;

            return ['data' => 'loaded'];
        },
    ]);

    expect($called)->toBeFalse();

    $data = json_decode($response->body(), true);
    expect($data['props']['user']['name'])->toBe('Test')
        ->and($data['props'])->not->toHaveKey('expensive');
});

test('inertia applies partial except headers with precedence over partial data', function () {
    $inertia = createInertia();
    $called = false;

    $request = new Request(server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'user',
        'HTTP_X_INERTIA_PARTIAL_EXCEPT' => 'stats',
    ]);

    $response = $inertia->render($request, 'Dashboard', [
        'user' => ['name' => 'Test'],
        'stats' => fn () => ['visits' => 100],
        'notifications' => function () use (&$called) {
            $called = true;

            return ['count' => 2];
        },
    ]);

    expect($called)->toBeTrue();

    $data = json_decode($response->body(), true);
    expect($data['props'])->toHaveKey('user')
        ->toHaveKey('notifications')
        ->not->toHaveKey('stats');
});

test('inertia keeps errors available during partial reloads', function () {
    $inertia = createInertia();

    $request = new Request(server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Login',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'form',
    ]);

    $response = $inertia->render($request, 'Login', [
        'form' => ['email' => 'demo@example.com'],
        'errors' => ['email' => 'Invalid email'],
        'expensive' => fn () => ['skipped' => false],
    ]);

    $data = json_decode($response->body(), true);
    expect($data['props'])->toHaveKey('form')
        ->and($data['props']['errors']['email'])->toBe('Invalid email')
        ->and($data['props'])->not->toHaveKey('expensive');
});

test('inertia includes flash in partial reloads', function () {
    $inertia = createInertia();
    $inertia->flash('success', 'Saved!');

    $request = new Request(server: [
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard',
        'HTTP_X_INERTIA_PARTIAL_DATA' => 'user',
    ]);

    $response = $inertia->render($request, 'Dashboard', [
        'user' => ['name' => 'Test'],
    ]);

    $data = json_decode($response->body(), true);
    expect($data['props']['flash']['success'])->toBe(['Saved!'])
        ->and($data['props']['user']['name'])->toBe('Test');
});

test('inertia clears flash after it is rendered once', function () {
    $inertia = createInertia();
    $inertia->flash('success', 'Saved!');

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    $first = json_decode($inertia->render($request, 'Dashboard')->body(), true);
    $second = json_decode($inertia->render($request, 'Dashboard')->body(), true);

    expect($first['props']['flash']['success'])->toBe(['Saved!'])
        ->and($second['props']['flash'])->toBeEmpty();
});

test('inertia html response escapes embedded page json safely', function () {
    $inertia = createInertia();
    $request = new Request();

    $response = $inertia->render($request, 'Dashboard', [
        'payload' => '</script><script>alert("xss")</script>',
    ]);

    expect($response->body())->not->toContain('</script><script>alert')
        ->toContain('\\u003C\\/script\\u003E');
});

test('inertia throws a loud exception for invalid version config', function () {
    $inertia = createInertia(['inertia.version' => ['invalid']]);

    expect(fn () => $inertia->version())
        ->toThrow(
            InertiaConfigurationException::class,
            'Inertia configuration key "inertia.version" must be a string, number, or null.',
        );
});

it('includes the query string in the Inertia page url', function (): void {
    $inertia = createInertia();
    $request = new Request(
        server: ['HTTP_X_INERTIA' => 'true', 'REQUEST_URI' => '/users?page=2'],
    );

    $response = $inertia->render($request, 'Users');
    $data = json_decode($response->body(), true);

    expect($data['url'])->toBe('/users?page=2');
});

it('reports only the path when the request has no query string', function (): void {
    $inertia = createInertia();
    $request = new Request(
        server: ['HTTP_X_INERTIA' => 'true', 'REQUEST_URI' => '/users'],
    );

    $response = $inertia->render($request, 'Users');
    $data = json_decode($response->body(), true);

    expect($data['url'])->toBe('/users');
});

it(
    'preserves a literal plus or percent-encoded space in the query string unchanged in the page url',
    function (): void {
        $inertia = createInertia();

        $requestPlus = new Request(
            server: ['HTTP_X_INERTIA' => 'true', 'REQUEST_URI' => '/search?q=hello+world'],
        );
        $requestEncoded = new Request(
            server: ['HTTP_X_INERTIA' => 'true', 'REQUEST_URI' => '/search?q=hello%20world'],
        );

        $dataPlus = json_decode($inertia->render($requestPlus, 'Search')->body(), true);
        $dataEncoded = json_decode($inertia->render($requestEncoded, 'Search')->body(), true);

        expect($dataPlus['url'])->toBe('/search?q=hello+world')
            ->and($dataEncoded['url'])->toBe('/search?q=hello%20world');
    },
);

class NullSsrTransport implements SsrTransportInterface
{
    public function post(
        string $url,
        string $body,
    ): ?string {
        return null;
    }
}
