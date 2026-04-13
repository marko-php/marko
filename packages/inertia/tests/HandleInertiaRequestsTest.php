<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\ComponentResolverInterface;
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Middleware\HandleInertiaRequests;
use Marko\Inertia\Props\PropsResolver;
use Marko\Inertia\Response\ResponseFactory;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

function makeMiddlewareAwareInertia(): Inertia
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

    $events = new class () implements EventDispatcherInterface
    {
        public function dispatch(Event $event): void {}
    };

    $config = new InertiaConfig(new ConfigRepository([
        'inertia' => [
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
        ],
    ]));

    return new Inertia(new ResponseFactory($config, $components, $renderer, $events, new PropsResolver()));
}

it('flushes shared props at the beginning of each request and adds the vary header', function (): void {
    $inertia = makeMiddlewareAwareInertia();
    $container = new Container();
    $inertia->share('stale', 'value');

    $middleware = new class ($inertia, $container) extends HandleInertiaRequests
    {
        public function __construct(
            Inertia $inertia,
            ContainerInterface $container,
        )
        {
            parent::__construct($inertia, $container);
        }

        protected function share(Request $request): array
        {
            return [
                'auth.user' => 'Taylor',
            ];
        }
    };

    $response = $middleware->handle(new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
    ), function (Request $request) use ($inertia): Response {
        return $inertia->render('Dashboard/Index', [], $request);
    });

    $payload = json_decode(html_entity_decode(strip_tags($response->body())), true, flags: JSON_THROW_ON_ERROR);

    expect($inertia->shared())->toBe([
        'auth' => ['user' => 'Taylor'],
    ])->and($response->headers()['Vary'])->toBe('X-Inertia')
        ->and($payload['props'])->toBe([
            'auth' => ['user' => 'Taylor'],
        ]);
});

it('shares once props', function (): void {
    $inertia = makeMiddlewareAwareInertia();
    $container = new Container();

    $middleware = new class ($inertia, $container) extends HandleInertiaRequests
    {
        public function __construct(
            Inertia $inertia,
            ContainerInterface $container,
        )
        {
            parent::__construct($inertia, $container);
        }

        protected function shareOnce(Request $request): array
        {
            return [
                'flashMessage' => 'Success!',
            ];
        }
    };

    $response = $middleware->handle(new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    ), function (Request $request) use ($inertia): Response {
        return $inertia->render('Dashboard/Index', [], $request);
    });

    $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['props'])->toHaveKey('flashMessage')
        ->and($payload['onceProps'])->toBe([
            'flashMessage' => ['prop' => 'flashMessage'],
        ]);
});

it('changes 302 redirects to 303 for PUT, PATCH, and DELETE requests', function (string $method): void {
    $inertia = makeMiddlewareAwareInertia();

    $middleware = new HandleInertiaRequests($inertia, new Container());

    $response = $middleware->handle(new Request(
        server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    ), function (Request $request): Response {
        return Response::redirect('/home');
    });

    expect($response->statusCode())->toBe(303)
        ->and($response->headers()['Location'])->toBe('/home');
})->with(['PUT', 'PATCH', 'DELETE']);

it('does not change 302 redirects to 303 for GET requests', function (): void {
    $inertia = makeMiddlewareAwareInertia();

    $middleware = new HandleInertiaRequests($inertia, new Container());

    $response = $middleware->handle(new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    ), function (Request $request): Response {
        return Response::redirect('/home');
    });

    expect($response->statusCode())->toBe(302);
});

it('returns a 409 conflict response for redirects with fragments', function (): void {
    $inertia = makeMiddlewareAwareInertia();

    $middleware = new HandleInertiaRequests($inertia, new Container());

    $response = $middleware->handle(new Request(
        server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'HTTP_X_INERTIA' => 'true',
        ],
    ), function (Request $request): Response {
        return Response::redirect('/home#section');
    });

    expect($response->statusCode())->toBe(409)
        ->and($response->headers()['X-Inertia-Location'])->toBe('/home#section');
});

it('registers the current request inertia instance for downstream resolution', function (): void {
    $inertia = makeMiddlewareAwareInertia();
    $container = new Container();
    $middleware = new HandleInertiaRequests($inertia, $container);

    $middleware->handle(new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dashboard'],
    ), function (Request $request) use ($container, $inertia): Response {
        expect($container->get(InertiaInterface::class))->toBe($inertia)
            ->and($container->get(Inertia::class))->toBe($inertia);

        return Response::html('ok');
    });
});
