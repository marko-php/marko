<?php

declare(strict_types=1);

use Marko\Core\Path\ProjectPaths;
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
        'inertia.rootView' => 'app',
        'inertia.version' => '1.0',
        'inertia.ssr.enabled' => false,
        'inertia.ssr.url' => 'http://localhost:13714',
        'vite.entry' => 'app/web/resources/js/app.js',
        'vite.buildDirectory' => 'build',
        'vite.manifestFilename' => '.vite/manifest.json',
        'vite.devServerUrl' => 'http://localhost:5173',
        'vite.devServerStylesheets' => [],
        'vite.useDevServer' => false,
    ], $config, array_combine(
        array_map(static fn (string $key): string => "vite.{$key}", array_keys($viteConfig)),
        array_values($viteConfig),
    ) ?: []));

    $paths = new ProjectPaths(dirname(__DIR__));
    $vite = new Vite($mergedConfig, $paths);
    $ssrClient = new SsrClient('http://localhost:13714', new NullSsrTransport());

    return new Inertia($mergedConfig, $vite, $ssrClient, new FakeSession());
}

test('inertia returns json for inertia requests', function () {
    $inertia = createInertia();
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    expect($response->statusCode())->toBe(200);
    expect($response->headers()['Content-Type'])->toBe('application/json');
    expect($response->headers()['Vary'])->toBe('X-Inertia');

    $data = json_decode($response->body(), true);
    expect($data['component'])->toBe('Dashboard');
    expect($data['props'])->toHaveKey('errors');
    expect($data['props']['user']['name'])->toBe('Test');
});

test('inertia returns html for non-inertia requests', function () {
    $inertia = createInertia();
    $request = new Request();

    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    expect($response->statusCode())->toBe(200);
    expect($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');
    expect($response->headers()['Vary'])->toBe('X-Inertia');
    expect($response->body())->toContain('<!DOCTYPE html>');
    expect($response->body())->toContain('<script data-page="app" type="application/json">');
    expect($response->body())->toContain('data-page=');
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

    expect($response->body())->toContain('http://localhost:5173/app/react-web/resources/js/app.jsx');
    expect($response->body())->not->toContain('app/web/resources/js/app.js');
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

    expect($response->body())->toContain('http://localhost:5173/app/admin/resources/js/admin.js');
    expect($response->body())->not->toContain('app/web/resources/js/app.js');
});

test('inertia merges shared data with page props', function () {
    $inertia = createInertia();
    $inertia->share('flash', ['message' => 'Hello']);

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $response = $inertia->render($request, 'Dashboard', ['user' => ['name' => 'Test']]);

    $data = json_decode($response->body(), true);
    expect($data['props']['flash']['message'])->toBe('Hello');
    expect($data['props']['user']['name'])->toBe('Test');
});

test('inertia location redirect returns x-inertia-location header', function () {
    $inertia = createInertia();
    $response = $inertia->location('https://example.com');

    expect($response->statusCode())->toBe(409);
    expect($response->headers()['Vary'])->toBe('X-Inertia');
    expect($response->headers()['X-Inertia-Location'])->toBe('https://example.com');
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
    expect($data['props']['user']['name'])->toBe('Test');
    expect($data['props'])->not->toHaveKey('expensive');
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
    expect($data['props'])->toHaveKey('user');
    expect($data['props'])->toHaveKey('notifications');
    expect($data['props'])->not->toHaveKey('stats');
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
    expect($data['props'])->toHaveKey('form');
    expect($data['props']['errors']['email'])->toBe('Invalid email');
    expect($data['props'])->not->toHaveKey('expensive');
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
    expect($data['props']['flash']['success'])->toBe(['Saved!']);
    expect($data['props']['user']['name'])->toBe('Test');
});

test('inertia clears flash after it is rendered once', function () {
    $inertia = createInertia();
    $inertia->flash('success', 'Saved!');

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    $first = json_decode($inertia->render($request, 'Dashboard')->body(), true);
    $second = json_decode($inertia->render($request, 'Dashboard')->body(), true);

    expect($first['props']['flash']['success'])->toBe(['Saved!']);
    expect($second['props']['flash'])->toBe([]);
});

test('inertia html response escapes embedded page json safely', function () {
    $inertia = createInertia();
    $request = new Request();

    $response = $inertia->render($request, 'Dashboard', [
        'payload' => '</script><script>alert("xss")</script>',
    ]);

    expect($response->body())->not->toContain('</script><script>alert');
    expect($response->body())->toContain('\\u003C\\/script\\u003E');
});

final class NullSsrTransport implements SsrTransportInterface
{
    public function post(string $url, string $body): ?string
    {
        return null;
    }
}
