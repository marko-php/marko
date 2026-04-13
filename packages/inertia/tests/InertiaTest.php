<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Inertia\ControllerLayoutPageMetadataResolver;
use Marko\Inertia\Enums\InertiaHeaderEnum;
use Marko\Inertia\Events\InertiaRenderingEvent;
use Marko\Inertia\Inertia;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\ComponentResolverInterface;
use Marko\Inertia\Interfaces\ProvidesInertiaProperties;
use Marko\Inertia\Interfaces\ProvidesScrollMetadata;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Props\PropsResolver;
use Marko\Inertia\Rendering\RenderContext;
use Marko\Inertia\Response\ResponseFactory;
use Marko\Routing\Http\Request;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

function fakeInertiaConfig(array $overrides = []): InertiaConfig
{
    return new InertiaConfig(new ConfigRepository([
        'inertia' => array_replace_recursive([
            'version' => 'v1',
            'root' => [
                'id' => 'app',
                'title' => 'Marko',
            ],
            'page' => [
                'ensure_pages_exist' => false,
                'paths' => ['resources/js/pages'],
                'extensions' => ['tsx'],
            ],
            'testing' => [
                'ensure_pages_exist' => false,
            ],
            'history' => [
                'encrypt' => false,
            ],
        ], $overrides),
    ]));
}

function makeInertia(
    ?callable $listener = null,
    array $configOverrides = [],
    ?callable $pageMetadataResolver = null,
): Inertia
{
    $components = new class () implements ComponentResolverInterface
    {
        public function resolve(string $component): string
        {
            return '/virtual/' . $component . '.tsx';
        }

        public function exists(string $component): bool
        {
            return true;
        }

        public function getSearchedPaths(string $component): array
        {
            return ['/virtual/' . $component . '.tsx'];
        }
    };

    $renderer = new class () implements RootRendererInterface
    {
        public function render(array $page): string
        {
            return '<html><body>' . htmlspecialchars(
                json_encode($page, JSON_THROW_ON_ERROR),
                ENT_QUOTES,
                'UTF-8',
            ) . '</body></html>';
        }
    };

    $events = new class ($listener) implements EventDispatcherInterface
    {
        public function __construct(
            private readonly mixed $listener,
        ) {}

        public function dispatch(Event $event): void
        {
            if ($this->listener !== null) {
                ($this->listener)($event);
            }
        }
    };

    $session = new class () implements SessionInterface
    {
        public bool $started = true;

        /** @var array<string, mixed> */
        private array $data = [];

        public function start(): void {}

        public function get(
            string $key,
            mixed $default = null,
        ): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void
        {
            $this->data[$key] = $value;
        }

        public function has(string $key): bool
        {
            return array_key_exists($key, $this->data);
        }

        public function remove(string $key): void
        {
            unset($this->data[$key]);
        }

        public function clear(): void
        {
            $this->data = [];
        }

        public function all(): array
        {
            return $this->data;
        }

        public function regenerate(bool $deleteOldSession = true): void {}

        public function destroy(): void
        {
            $this->data = [];
            $this->started = false;
        }

        public function getId(): string
        {
            return 'test-session';
        }

        public function setId(string $id): void {}

        public function flash(): FlashBag
        {
            return new FlashBag($this->data);
        }

        public function save(): void {}
    };

    return new Inertia(new ResponseFactory(
        fakeInertiaConfig($configOverrides),
        $components,
        $renderer,
        $events,
        new PropsResolver(),
        static fn () => $session,
        $pageMetadataResolver,
    ));
}

class InertiaLayoutComponent {}

#[\Marko\Layout\Attributes\Layout(InertiaLayoutComponent::class)]
class InertiaLayoutController
{
    public function index(): void {}
}

#[\Marko\Layout\Attributes\Layout('showcase::ShowcaseDemoLayout')]
class InertiaNamedLayoutController
{
    public function index(): void {}
}

it('renders an html bootstrap response for first visits', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
    );

    $response = $inertia->render('Dashboard/Index', ['title' => 'Dashboard'], $request);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
        ->and($response->body())->toContain('&quot;component&quot;:&quot;Dashboard\\/Index&quot;')
        ->and($response->body())->toContain('&quot;url&quot;:&quot;\\/dashboard&quot;');
});

it('renders json for inertia requests and merges shared props', function (): void {
    $inertia = makeInertia();
    $inertia->share('auth.user', 'Taylor');
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $response = $inertia->render('Dashboard/Index', ['stats' => [1, 2, 3]], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($response->headers()[InertiaHeaderEnum::INERTIA->value])->toBe('true')
        ->and($response->headers()['Vary'])->toBe(InertiaHeaderEnum::INERTIA->value)
        ->and($payload['props'])->toBe([
            'auth' => ['user' => 'Taylor'],
            'stats' => [1, 2, 3],
        ]);
});

it('allows observers to contribute shared props during rendering', function (): void {
    $inertia = makeInertia(function (Event $event): void {
        if ($event instanceof InertiaRenderingEvent) {
            $event->share('ziggy', ['location' => '/dashboard']);
        }
    });

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toHaveKey('ziggy')
        ->and($payload['props']['ziggy']['location'])->toBe('/dashboard');
});

it('merges page metadata resolver props without overwriting existing page props', function (): void {
    $inertia = makeInertia(
        pageMetadataResolver: static fn (mixed ...$args): array => [
            'props' => [
                '_marko' => [
                    'layout' => [
                        'name' => 'admin-panel::AdminLayout',
                    ],
                ],
            ],
        ],
    );

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $response = $inertia->render('Dashboard/Index', ['stats' => [1, 2, 3]], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props']['stats'])->toBe([1, 2, 3])
        ->and($payload['props']['_marko']['layout']['name'])->toBe('admin-panel::AdminLayout');
});

it('resolves controller layout metadata for inertia pages when marko layout is available', function (): void {
    $resolver = new ControllerLayoutPageMetadataResolver(
        new ModuleRepository([]),
        new ProjectPaths(sys_get_temp_dir()),
    );

    $metadata = $resolver->resolve(
        InertiaLayoutController::class,
        'index',
    );

    expect($metadata['props']['_marko']['layout']['component'])->toBe(InertiaLayoutComponent::class)
        ->and($metadata['props']['_marko']['layout']['name'])->toBe('InertiaLayoutComponent');
});

it('passes through string layout identifiers for inertia-only routes', function (): void {
    $resolver = new ControllerLayoutPageMetadataResolver(
        new ModuleRepository([]),
        new ProjectPaths(sys_get_temp_dir()),
    );

    $metadata = $resolver->resolve(
        InertiaNamedLayoutController::class,
        'index',
    );

    expect($metadata['props']['_marko']['layout'])->toBe([
        'component' => 'showcase::ShowcaseDemoLayout',
    ]);
});

it('supports partial reload headers for matching components', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard/Index',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'stats',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'flash' => ['ok' => true],
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toBe([
        'stats' => [1, 2, 3],
    ]);
});

it('excludes optional props from full responses and resolves them on partial reloads', function (): void {
    $inertia = makeInertia();

    $initialRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $initialResponse = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'users' => $inertia->optional(static fn (): array => ['Taylor', 'Jeffrey']),
    ], $initialRequest);
    $initialPayload = json_decode($initialResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    $partialRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard/Index',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'users',
        ],
    );

    $partialResponse = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'users' => $inertia->optional(static fn (): array => ['Taylor', 'Jeffrey']),
    ], $partialRequest);
    $partialPayload = json_decode($partialResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($initialPayload['props'])->toBe([
        'stats' => [1, 2, 3],
    ])->and($partialPayload['props'])->toBe([
        'users' => ['Taylor', 'Jeffrey'],
    ]);
});

it('keeps always props present during partial reloads', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard/Index',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'stats',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'csrf' => $inertia->always('token-123'),
        'flash' => ['ok' => true],
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toBe([
        'stats' => [1, 2, 3],
        'csrf' => 'token-123',
    ]);
});

it('collects deferred prop metadata and excludes deferred props from the initial response', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'users' => $inertia->defer(static fn (): array => ['Taylor', 'Jeffrey'], 'sidebar'),
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toBe([
        'stats' => [1, 2, 3],
    ])->and($payload['deferredProps'])->toBe([
        'sidebar' => ['users'],
    ]);
});

it('resolves deferred props when requested by a partial reload', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_PARTIAL_COMPONENT' => 'Dashboard/Index',
            'HTTP_X_INERTIA_PARTIAL_DATA' => 'users',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'users' => $inertia->defer(static fn (): array => ['Taylor', 'Jeffrey'], 'sidebar'),
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toBe([
        'users' => ['Taylor', 'Jeffrey'],
    ]);
});

it('collects merge metadata for mergeable props', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/feed',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $response = $inertia->render('Feed/Index', [
        'posts' => $inertia->merge([
            ['id' => 1, 'title' => 'First'],
        ]),
        'notifications' => $inertia->merge([
            ['id' => 2, 'text' => 'Hello'],
        ])->prepend(),
        'filters' => $inertia->deepMerge([
            'status' => 'published',
        ]),
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['mergeProps'])->toBe(['posts'])
        ->and($payload['prependProps'])->toBe(['notifications'])
        ->and($payload['deepMergeProps'])->toBe(['filters']);
});

it('collects once metadata and omits once props already loaded by the client', function (): void {
    $inertia = makeInertia();

    $initialRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $initialResponse = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'credits' => $inertia->once(static fn (): string => 'one-time', 'credits-banner'),
    ], $initialRequest);
    $initialPayload = json_decode($initialResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    $repeatRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_EXCEPT_ONCE' => 'credits-banner',
        ],
    );

    $repeatResponse = $inertia->render('Dashboard/Index', [
        'stats' => [1, 2, 3],
        'credits' => $inertia->once(static fn (): string => 'one-time', 'credits-banner'),
    ], $repeatRequest);
    $repeatPayload = json_decode($repeatResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($initialPayload['props'])->toHaveKey('credits')
        ->and($initialPayload['onceProps'])->toBe([
            'credits-banner' => ['prop' => 'credits'],
        ])
        ->and($repeatPayload['props'])->toBe([
            'stats' => [1, 2, 3],
        ])
        ->and($repeatPayload['onceProps'])->toBe([
            'credits-banner' => ['prop' => 'credits'],
        ]);
});

it('returns an inertia location response for version mismatches', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'stale-version',
        ],
    );

    $response = $inertia->render('Dashboard/Index', [], $request);

    expect($response->statusCode())->toBe(409)
        ->and($response->headers()[InertiaHeaderEnum::LOCATION->value])->toBe('/dashboard');
});

it('returns a normal redirect for non-inertia location visits', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
    );

    $response = $inertia->location('/login', $request);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/login');
});

it('includes flashed data on the next page response and then clears it', function (): void {
    $inertia = makeInertia();
    $inertia->flash([
        'success' => 'Saved successfully.',
        'highlight' => ['post-42'],
    ]);

    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $firstResponse = $inertia->render('Dashboard/Index', [], $request);
    $firstPayload = json_decode($firstResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    $secondResponse = $inertia->render('Dashboard/Index', [], $request);
    $secondPayload = json_decode($secondResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($firstPayload['flash'])->toBe([
        'success' => 'Saved successfully.',
        'highlight' => ['post-42'],
    ])->and($secondPayload)->not->toHaveKey('flash');
});

it('does not consume flashed data when a version mismatch returns an inertia location response', function (): void {
    $inertia = makeInertia();
    $inertia->flash('success', 'Saved successfully.');

    $mismatchRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'stale-version',
        ],
    );

    $mismatchResponse = $inertia->render('Dashboard/Index', [], $mismatchRequest);

    $freshRequest = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'v1',
        ],
    );

    $freshResponse = $inertia->render('Dashboard/Index', [], $freshRequest);
    $freshPayload = json_decode($freshResponse->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($mismatchResponse->statusCode())->toBe(409)
        ->and($freshPayload['flash'])->toBe([
            'success' => 'Saved successfully.',
        ]);
});

it('expands top-level property providers into response props', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $provider = new class () implements ProvidesInertiaProperties
    {
        public function toInertiaProperties(RenderContext $context): array
        {
            return [
                'meta' => [
                    'component' => $context->component,
                    'path' => $context->request->path(),
                ],
                'auth.user' => 'Taylor',
            ];
        }
    };

    $response = $inertia->render('Dashboard/Index', [
        $provider,
        'stats' => [1, 2, 3],
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toBe([
        'meta' => [
            'component' => 'Dashboard/Index',
            'path' => '/dashboard',
        ],
        'auth' => [
            'user' => 'Taylor',
        ],
        'stats' => [1, 2, 3],
    ]);
});

it('collects scroll metadata and wraps scroll prop values', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/feed',
            'HTTP_X_INERTIA' => 'true',
        ],
    );

    $metadata = new class () implements ProvidesScrollMetadata
    {
        public function toScrollMetadata(RenderContext $context): array
        {
            return [
                'component' => $context->component,
                'pageName' => 'posts',
                'previousPage' => null,
                'nextPage' => '/feed?page=2',
            ];
        }
    };

    $response = $inertia->render('Feed/Index', [
        'posts' => $inertia->scroll([
            ['id' => 1, 'title' => 'First'],
        ], 'data', $metadata),
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props']['posts'])->toBe([
        'data' => [
            ['id' => 1, 'title' => 'First'],
        ],
    ])->and($payload['scrollProps'])->toBe([
        'posts' => [
            'component' => 'Feed/Index',
            'pageName' => 'posts',
            'previousPage' => null,
            'nextPage' => '/feed?page=2',
            'reset' => false,
        ],
    ])->and($payload['mergeProps'])->toContain('posts');
});

it('marks scroll metadata as reset when requested by the client', function (): void {
    $inertia = makeInertia();
    $request = new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/feed',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_RESET' => 'posts',
        ],
    );

    $response = $inertia->render('Feed/Index', [
        'posts' => $inertia->scroll([
            ['id' => 1, 'title' => 'First'],
        ], 'data', [
            'pageName' => 'posts',
        ]),
    ], $request);
    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['scrollProps'])->toBe([
        'posts' => [
            'pageName' => 'posts',
            'reset' => true,
        ],
    ])->and($payload)->not->toHaveKey('mergeProps');
});
