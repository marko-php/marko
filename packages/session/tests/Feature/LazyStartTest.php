<?php

declare(strict_types=1);

namespace Marko\Session\Tests\Feature;

use Marko\Core\Container\Container;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * Spies on every call the session layer makes to its storage handler.
 */
class LazyStartSpyHandler implements SessionHandlerInterface
{
    /** @var array<int, string> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $stored = [];

    public function open(
        string $path,
        string $name,
    ): bool {
        $this->calls[] = 'open';

        return true;
    }

    public function close(): bool
    {
        $this->calls[] = 'close';

        return true;
    }

    public function read(
        string $id,
    ): string|false {
        $this->calls[] = 'read';

        return $this->stored[$id] ?? '';
    }

    public function write(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'write';
        $this->stored[$id] = $data;

        return true;
    }

    public function destroy(
        string $id,
    ): bool {
        $this->calls[] = 'destroy';
        unset($this->stored[$id]);

        return true;
    }

    public function gc(
        int $max_lifetime,
    ): int|false {
        $this->calls[] = 'gc';

        return 0;
    }

    public function validateId(
        string $id,
    ): bool {
        $this->calls[] = 'validateId';

        return isset($this->stored[$id]);
    }

    public function updateTimestamp(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'updateTimestamp';

        return true;
    }
}

readonly class LazyStartController
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/')]
    public function home(): Response
    {
        return new Response('home');
    }

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/peek')]
    public function peek(): Response
    {
        $loggedIn = $this->session->has('user_id');
        $cart = $this->session->get('cart', []);

        return new Response($loggedIn ? 'user' : 'guest:' . count($cart));
    }

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/flash')]
    public function flash(): Response
    {
        $this->session->flash()->add('success', 'Saved');

        return new Response('flash');
    }

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/cart')]
    public function cart(): Response
    {
        $this->session->set('cart', ['sku-1']);

        return new Response('cart');
    }
}

/**
 * GC is configured to run on every session start, so any start shows up as
 * a gc() call on the spy.
 *
 * @return array{router: Router, handler: LazyStartSpyHandler, session: Session}
 */
function lazyStartHarness(): array
{
    $handler = new LazyStartSpyHandler();
    $config = new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'array',
        'session.lifetime' => 120,
        'session.expire_on_close' => false,
        'session.path' => '/tmp',
        'session.cookie.name' => 'marko_session',
        'session.cookie.path' => '/',
        'session.cookie.domain' => '',
        'session.cookie.secure' => true,
        'session.cookie.httponly' => true,
        'session.cookie.samesite' => 'lax',
        'session.gc_probability' => 100,
        'session.gc_divisor' => 100,
    ]));
    $session = new Session($handler, $config);

    $container = new Container();
    $container->instance(SessionConfig::class, $config);
    $container->instance(SessionInterface::class, $session);
    $container->instance(ClockInterface::class, new FakeClock());

    $routes = new RouteCollection();

    foreach ((new RouteDiscovery())->discoverFromClass(LazyStartController::class) as $route) {
        $routes->add($route);
    }

    return [
        'router' => new Router(new RouteMatcher($routes), $container, [SessionMiddleware::class]),
        'handler' => $handler,
        'session' => $session,
    ];
}

function lazyGet(
    string $path,
    ?string $sessionCookie = null,
): Request {
    return new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path],
        cookies: $sessionCookie === null ? [] : ['marko_session' => $sessionCookie],
    );
}

describe('lazy session start', function (): void {
    it('makes zero handler calls for a cookieless request that never touches the session', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        $response = $router->handle(lazyGet('/'));

        expect($response->body())->toBe('home')
            ->and($response->cookies())->toBeEmpty()
            ->and($handler->calls)->toBe([]);
    });

    it('starts, saves and sends a cookie for a cookieless request that stores a value', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        $response = $router->handle(lazyGet('/cart'));

        $cookie = $response->cookies()[0];

        expect($response->cookies())->toHaveCount(1)
            ->and($cookie->name())->toBe('marko_session')
            ->and($handler->calls)->toContain('open', 'read', 'write')
            ->and($handler->stored)->toHaveKey($cookie->value())
            ->and($handler->stored[$cookie->value()])->toContain('sku-1');
    });

    it('reads lazily, stores nothing and sends no cookie for a cookieless request that only reads', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        $response = $router->handle(lazyGet('/peek'));

        expect($response->body())->toBe('guest:0')
            ->and($response->cookies())->toBeEmpty()
            ->and($handler->calls)->toContain('read')
            ->and($handler->calls)->not->toContain('write')
            ->and($handler->stored)->toBeEmpty();
    });

    it('persists a flash message set on a cookieless request', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        $response = $router->handle(lazyGet('/flash'));

        $sessionId = $response->cookies()[0]->value();

        expect($handler->stored)->toHaveKey($sessionId)
            ->and($handler->stored[$sessionId])->toContain('Saved');
    });

    it('runs no garbage collection on cookieless requests that never touch the session', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        for ($i = 0; $i < 3; $i++) {
            $router->handle(lazyGet('/'));
        }

        expect($handler->calls)->not->toContain('gc');
    });

    it('expires a malformed cookie without calling the handler', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();

        $response = $router->handle(lazyGet('/', 'not a valid id!'));

        expect($response->cookies())->toHaveCount(1)
            ->and($response->cookies()[0]->value())->toBe('')
            ->and($handler->calls)->toBe([]);
    });

    it('still reads a resumed session before the controller runs', function (): void {
        ['router' => $router, 'handler' => $handler] = lazyStartHarness();
        $knownId = str_repeat('c', 40);
        $handler->stored[$knownId] = 'cart|a:1:{i:0;s:5:"sku-1";}';

        $response = $router->handle(lazyGet('/', $knownId));

        expect($response->cookies())->toBeEmpty()
            ->and($handler->calls)->toContain('validateId', 'read', 'updateTimestamp');
    });

    it('leaves the session unavailable once the request completes', function (): void {
        ['router' => $router, 'session' => $session] = lazyStartHarness();

        $router->handle(lazyGet('/'));

        expect($session->isAvailable())->toBeFalse()
            ->and($session->started)->toBeFalse();
    });
});
