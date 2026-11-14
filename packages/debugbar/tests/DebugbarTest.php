<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Path\ProjectPaths;
use Marko\Debugbar\Access\AccessGate;
use Marko\Debugbar\Collectors\ResponseCollector;
use Marko\Debugbar\Controller\ProfilerController;
use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Plugins\DatabaseConnectionPlugin;
use Marko\Debugbar\Plugins\LoggerPlugin;
use Marko\Debugbar\Plugins\ViewPlugin;
use Marko\Debugbar\Storage\DebugbarStorage;
use Marko\Log\LogLevel;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

beforeEach(function (): void {
    Debugbar::forgetCurrent();

    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/dashboard?tab=debug',
        'HTTP_ACCEPT' => 'text/html',
        'HTTP_AUTHORIZATION' => 'Bearer secret-token',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    $_GET = ['tab' => 'debug'];
    $_POST = ['password' => 'secret'];
});

function makeDebugbar(
    array $overrides = [],
    ?DebugbarStorage &$storage = null,
): Debugbar {
    $basePath = sys_get_temp_dir().'/marko-debugbar-test-'.bin2hex(random_bytes(4));

    $config = array_replace_recursive([
        'debugbar' => [
            'enabled' => true,
            'inject' => true,
            'capture_cli' => true,
            'theme' => 'auto',
            'route' => [
                'open' => false,
                'allowed_ips' => ['127.0.0.1', '::1'],
            ],
            'storage' => [
                'enabled' => true,
                'path' => 'storage/debugbar',
                'max_files' => 100,
            ],
            'collectors' => [
                'messages' => true,
                'time' => true,
                'memory' => true,
                'request' => true,
                'response' => true,
                'inertia' => true,
                'views' => true,
                'database' => true,
                'logs' => true,
                'config' => false,
            ],
            'options' => [
                'messages' => [
                    'trace' => false,
                ],
                'config' => [
                    'masked' => [
                        '*.key',
                        '*.password',
                        '*.token',
                    ],
                ],
                'database' => [
                    'with_bindings' => true,
                    'slow_threshold_ms' => 100,
                ],
            ],
        ],
        'app' => [
            'key' => 'base64:secret',
            'name' => 'Marko',
        ],
    ], $overrides);

    $repository = new ConfigRepository($config);
    $storage = new DebugbarStorage($repository, new ProjectPaths($basePath));

    return new Debugbar(
        $repository,
        $storage,
        access: new AccessGate($repository, new AppEnvironment(['APP_ENV' => 'local'])),
    );
}

function makeProfilerController(
    ConfigRepositoryInterface $config,
    DebugbarStorage $storage,
    string $environment = 'local',
): ProfilerController {
    return new ProfilerController(
        $config,
        $storage,
        access: new AccessGate($config, new AppEnvironment(['APP_ENV' => $environment])),
    );
}

test('debugbar injects itself before the closing body tag', function (): void {
    $debugbar = makeDebugbar();
    $debugbar->addMessage('Loaded dashboard');

    $html = '<!doctype html><html><body><main>Dashboard</main></body></html>';
    $injected = $debugbar->inject($html);

    expect($injected)
        ->toContain('marko-debugbar')
        ->toContain('data-marko-debugbar-state="collapsed"')
        ->toContain('data-marko-debugbar-toggle')
        ->toContain('target="_blank"')
        ->toContain('href="/_debugbar"')
        ->toContain('All requests')
        ->toContain('Loaded dashboard')
        ->and(strpos($injected, 'marko-debugbar'))->toBeLessThan(strpos($injected, '</body>'));
});

test('debugbar renders scalar collector values', function (): void {
    $debugbar = makeDebugbar();

    $injected = $debugbar->inject('<html><body>Page</body></html>');

    expect($injected)
        ->toContain('<pre>GET</pre>')
        ->toContain('<pre>/dashboard?tab=debug</pre>')
        ->not->toContain('<pre>string</pre>');
});

test('debugbar does not inject into json responses', function (): void {
    $storage = null;
    $debugbar = makeDebugbar([], $storage);

    $json = '{"ok":true}';

    expect($debugbar->inject($json))->toBe($json)
        ->and($storage?->get($debugbar->id()))->toBeArray()
        ->and($storage?->get($debugbar->id())['collectors']['response']['body_type'])->toBe('json');
});

test('debugbar detects inertia responses without requiring inertia as a dependency', function (): void {
    $debugbar = makeDebugbar();

    $json = json_encode([
        'component' => 'Dashboard/Index',
        'props' => [
            'users' => [],
            'filters' => [],
        ],
        'url' => '/dashboard',
        'version' => 'abc123',
    ], JSON_THROW_ON_ERROR);

    $dataset = $debugbar->collect($json);

    expect($dataset['collectors']['inertia']['component'])->toBe('Dashboard/Index')
        ->and($dataset['collectors']['inertia']['props_count'])->toBe(2)
        ->and($dataset['collectors']['inertia']['prop_keys'])->toBe(['users', 'filters']);
});

test('debugbar does not collect or inject when disabled', function (): void {
    $debugbar = makeDebugbar([
        'debugbar' => [
            'enabled' => false,
        ],
    ]);

    $debugbar->addMessage('Hidden');

    expect($debugbar->messages())->toBe([])
        ->and($debugbar->inject('<html><body>Page</body></html>'))->toBe('<html><body>Page</body></html>');
});

test('debugbar helper writes messages to the current instance', function (): void {
    $debugbar = makeDebugbar();

    debugbar('Payment attempted', 'warning', ['invoice' => 123]);

    expect(Debugbar::current())->toBe($debugbar)
        ->and($debugbar->messages())->toHaveCount(1)
        ->and($debugbar->messages()[0]->message)->toBe('Payment attempted')
        ->and($debugbar->messages()[0]->level)->toBe('warning');
});

test('debugbar records custom measures', function (): void {
    $debugbar = makeDebugbar();

    $result = $debugbar->measure('expensive work', static fn (): string => 'done');

    expect($result)->toBe('done')
        ->and($debugbar->measures())->toHaveCount(1)
        ->and($debugbar->measures()[0]->name)->toBe('expensive work')
        ->and($debugbar->measures()[0]->durationMs())->toBeGreaterThanOrEqual(0.0);
});

test('debugbar records database queries', function (): void {
    $debugbar = makeDebugbar();
    $debugbar->recordQuery(
        type: 'query',
        sql: 'select * from users where id = ?',
        bindings: [42],
        start: microtime(true),
        durationMs: 3.25,
        rows: 1,
    );

    $dataset = $debugbar->collect();

    expect($debugbar->queries())->toHaveCount(1)
        ->and($dataset['collectors']['database']['count'])->toBe(1)
        ->and($dataset['collectors']['database']['queries'][0]['sql'])->toBe('select * from users where id = ?')
        ->and($dataset['collectors']['database']['queries'][0]['bindings'])->toBe([42]);
});

test('database plugin records query and execute calls', function (): void {
    $debugbar = makeDebugbar();
    $plugin = new DatabaseConnectionPlugin($debugbar);

    $plugin->beforeQuery('select * from users', []);
    $plugin->afterQuery([['id' => 1], ['id' => 2]], 'select * from users', []);

    $plugin->beforeExecute('delete from sessions where id = ?', ['abc']);
    $result = $plugin->afterExecute(1, 'delete from sessions where id = ?', ['abc']);

    expect($result)->toBe(1)
        ->and($debugbar->queries())->toHaveCount(2)
        ->and($debugbar->queries()[0]->type)->toBe('query')
        ->and($debugbar->queries()[0]->rows)->toBe(2)
        ->and($debugbar->queries()[1]->type)->toBe('execute')
        ->and($debugbar->queries()[1]->rows)->toBe(1);
});

test('debugbar records logs', function (): void {
    $debugbar = makeDebugbar();
    $debugbar->recordLog('warning', 'Cache miss', ['key' => 'users']);

    $dataset = $debugbar->collect();

    expect($debugbar->logs())->toHaveCount(1)
        ->and($dataset['collectors']['logs']['logs'][0]['level'])->toBe('warning')
        ->and($dataset['collectors']['logs']['logs'][0]['message'])->toBe('Cache miss');
});

test('view plugin records latte and view renders', function (): void {
    $debugbar = makeDebugbar();
    $plugin = new ViewPlugin($debugbar);
    $response = Response::html('<h1>Dashboard</h1>');

    $plugin->beforeRender('app::dashboard/index', ['user' => ['id' => 1]]);
    $plugin->afterRender($response, 'app::dashboard/index', ['user' => ['id' => 1]]);

    $plugin->beforeRenderToString('mail::welcome', ['name' => 'Ada']);
    $plugin->afterRenderToString('<p>Hello Ada</p>', 'mail::welcome', ['name' => 'Ada']);

    $dataset = $debugbar->collect();

    expect($debugbar->viewRenders())->toHaveCount(2)
        ->and($dataset['collectors']['views']['count'])->toBe(2)
        ->and($dataset['collectors']['views']['renders'][0]['template'])->toBe('app::dashboard/index')
        ->and($dataset['collectors']['views']['renders'][0]['data_keys'])->toBe(['user']);
});

test('profiler controller renders stored requests and json snapshots', function (): void {
    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $debugbar->addMessage('Stored request');
    $debugbar->inject('<html><body>Page</body></html>');

    expect($storage)->toBeInstanceOf(DebugbarStorage::class);

    $controller = makeProfilerController($debugbar->config(), $storage);
    $request = new Request();

    $index = $controller->index($request);
    $show = $controller->show($request, $debugbar->id());
    $json = $controller->json($request, $debugbar->id());

    expect($index->statusCode())->toBe(200)
        ->and($index->body())->toContain('Request Profiler')
        ->and($index->body())->toContain('Stored Requests')
        ->and($show->body())->toContain('Stored request')
        ->and($show->body())->toContain('Collectors')
        ->and($json->headers()['Content-Type'])->toBe('application/json')
        ->and($json->body())->toContain($debugbar->id());
});

test('logger plugin records log calls and convenience level calls', function (): void {
    $debugbar = makeDebugbar();
    $plugin = new LoggerPlugin($debugbar);

    $plugin->afterLog(null, LogLevel::Error, 'Payment failed', ['invoice' => 123]);
    $plugin->afterInfo(null, 'Report generated', ['rows' => 5]);

    expect($debugbar->logs())->toHaveCount(2)
        ->and($debugbar->logs()[0]->level)->toBe('error')
        ->and($debugbar->logs()[0]->message)->toBe('Payment failed')
        ->and($debugbar->logs()[1]->level)->toBe('info')
        ->and($debugbar->logs()[1]->message)->toBe('Report generated');
});

test('debugbar masks request secrets and optional config secrets', function (): void {
    $debugbar = makeDebugbar([
        'debugbar' => [
            'collectors' => [
                'config' => true,
            ],
        ],
        'services' => [
            'mail' => [
                'token' => 'secret-token',
            ],
        ],
    ]);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['request']['post']['password'])->toBe('[masked]')
        ->and($dataset['collectors']['request']['headers']['Authorization'])->toBe('[masked]')
        ->and($dataset['collectors']['config']['config']['app']['key'])->toBe('[masked]')
        ->and($dataset['collectors']['config']['config']['services']['mail']['token'])->toBe('[masked]');
});

test('boot starts capture in cli only when configured', function (): void {
    $debugbar = makeDebugbar([
        'debugbar' => [
            'capture_cli' => false,
        ],
    ]);

    $debugbar->boot();

    expect($debugbar->isCapturing())->toBeFalse();
});

test('debugbar masks cookie values in the toolbar html and the stored profile', function (): void {
    $_SERVER['HTTP_COOKIE'] = 'marko_session=sess-abc123; remember_me=remember-xyz';

    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $injected = $debugbar->inject('<html><body>Page</body></html>');
    $stored = $storage?->get($debugbar->id());

    expect($stored['collectors']['request']['headers']['Cookie'])
        ->toBe('marko_session=[masked]; remember_me=[masked]')
        ->and($injected)->not->toContain('sess-abc123')
        ->and($injected)->not->toContain('remember-xyz')
        ->and(json_encode($stored, JSON_THROW_ON_ERROR))->not->toContain('sess-abc123');
});

test('response collector masks set-cookie values', function (): void {
    $collected = (new ResponseCollector())->collect('<html></html>', [
        'Content-Type: text/html',
        'Set-Cookie: marko_session=new-session-id; Path=/; HttpOnly',
    ]);

    expect($collected['headers'])->toBe([
        'Content-Type: text/html',
        'Set-Cookie: marko_session=[masked]; Path=/; HttpOnly',
    ]);
});

test('debugbar masks sensitive query parameters and the request uri', function (): void {
    $_SERVER['REQUEST_URI'] = '/reset?token=reset-secret&page=2';
    $_GET = ['token' => 'reset-secret', 'page' => '2'];

    $dataset = makeDebugbar()->collect();

    expect($dataset['collectors']['request']['query'])->toBe(['token' => '[masked]', 'page' => '2'])
        ->and($dataset['collectors']['request']['uri'])->toBe('/reset?token=[masked]&page=2')
        ->and($dataset['summary']['uri'])->toBe('/reset?token=[masked]&page=2');
});

test('debugbar masks sensitive keys in message and log context', function (): void {
    $debugbar = makeDebugbar();
    $debugbar->addMessage('Login', 'info', ['user' => 'ada', 'password' => 'hunter2']);
    $debugbar->recordLog('info', 'API call', ['headers' => ['Authorization' => 'Bearer abc']]);

    $dataset = $debugbar->collect();

    expect($dataset['collectors']['messages']['messages'][0]['context'])
        ->toBe(['user' => 'ada', 'password' => '[masked]'])
        ->and($dataset['collectors']['logs']['logs'][0]['context'])
        ->toBe(['headers' => ['Authorization' => '[masked]']]);
});

test('debugbar does not inject or store for a client outside the allowlist', function (): void {
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $html = '<html><body>Page</body></html>';

    expect($debugbar->inject($html))->toBe($html)
        ->and($storage?->get($debugbar->id()))->toBeNull();
});

test('debugbar does not inject when a same-host proxy forwards an external client', function (): void {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';

    $html = '<html><body>Page</body></html>';

    expect(makeDebugbar()->inject($html))->toBe($html);
});

test('debugbar does not inject when REMOTE_ADDR is missing from an http request', function (): void {
    unset($_SERVER['REMOTE_ADDR']);

    $html = '<html><body>Page</body></html>';

    expect(makeDebugbar()->inject($html))->toBe($html);
});

test('debugbar is disabled in production unless explicitly allowed', function (): void {
    $production = new AppEnvironment(['APP_ENV' => 'production']);
    $html = '<html><body>Page</body></html>';

    $deniedConfig = new ConfigRepository(['debugbar' => ['enabled' => true, 'allow_production' => false]]);
    $denied = new Debugbar($deniedConfig, access: new AccessGate($deniedConfig, $production));

    $allowedConfig = new ConfigRepository(['debugbar' => ['enabled' => true, 'allow_production' => true]]);
    $allowed = new Debugbar($allowedConfig, access: new AccessGate($allowedConfig, $production));

    expect($denied->isEnabled())->toBeFalse()
        ->and($denied->inject($html))->toBe($html)
        ->and($allowed->isEnabled())->toBeTrue()
        ->and($allowed->inject($html))->toContain('marko-debugbar');
});

test('profiler routes return 404 in production', function (): void {
    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $debugbar->inject('<html><body>Page</body></html>');

    $controller = makeProfilerController($debugbar->config(), $storage, 'production');

    expect($controller->index(new Request())->statusCode())->toBe(404)
        ->and($controller->json(new Request(), $debugbar->id())->statusCode())->toBe(404)
        ->and($controller->clear(new Request())->statusCode())->toBe(404);
});

test('profiler routes return 404 when REMOTE_ADDR is missing', function (): void {
    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $debugbar->inject('<html><body>Page</body></html>');

    unset($_SERVER['REMOTE_ADDR']);

    $controller = makeProfilerController($debugbar->config(), $storage);

    expect($controller->index(new Request())->statusCode())->toBe(404)
        ->and($controller->json(new Request(), $debugbar->id())->statusCode())->toBe(404);
});

test('profiler routes return 404 behind an untrusted same-host proxy', function (): void {
    $storage = null;
    $debugbar = makeDebugbar([], $storage);
    $debugbar->inject('<html><body>Page</body></html>');

    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';

    $controller = makeProfilerController($debugbar->config(), $storage);

    expect($controller->index(new Request())->statusCode())->toBe(404)
        ->and($controller->clear(new Request())->statusCode())->toBe(404);
});
