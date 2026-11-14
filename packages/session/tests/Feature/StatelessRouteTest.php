<?php

declare(strict_types=1);

namespace Marko\Session\Tests\Feature;

use Marko\Core\Container\Container;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\WithoutMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDiscovery;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\SessionNotStartedException;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

/**
 * Records every call the session layer makes to its storage handler.
 */
class RecordingSessionHandler implements SessionHandlerInterface
{
    /** @var array<int, string> */
    public array $calls = [];

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

        return '';
    }

    public function write(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'write';

        return true;
    }

    public function destroy(
        string $id,
    ): bool {
        $this->calls[] = 'destroy';

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

        return false;
    }

    public function updateTimestamp(
        string $id,
        string $data,
    ): bool {
        $this->calls[] = 'updateTimestamp';

        return true;
    }
}

readonly class StatelessApiController
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/api/health')]
    #[WithoutMiddleware(SessionMiddleware::class)]
    public function health(): Response
    {
        return Response::json(['status' => 'ok']);
    }

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/api/whoami')]
    #[WithoutMiddleware(SessionMiddleware::class)]
    public function whoami(): Response
    {
        return new Response((string) $this->session->get('user_id'));
    }

    /** @noinspection PhpUnused - Invoked by the router */
    #[Get('/dashboard')]
    public function dashboard(): Response
    {
        $this->session->set('visited', true);

        return new Response('dashboard');
    }
}

/**
 * @return array{router: Router, handler: RecordingSessionHandler}
 */
function statelessRouteHarness(): array
{
    $handler = new RecordingSessionHandler();
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
        'session.gc_probability' => 0,
        'session.gc_divisor' => 100,
    ]));

    $container = new Container();
    $container->instance(SessionConfig::class, $config);
    $container->instance(SessionInterface::class, new Session($handler, $config));
    $container->instance(ClockInterface::class, new FakeClock());

    $routes = new RouteCollection();

    foreach ((new RouteDiscovery())->discoverFromClass(StatelessApiController::class) as $route) {
        $routes->add($route);
    }

    return [
        'router' => new Router(new RouteMatcher($routes), $container, [SessionMiddleware::class]),
        'handler' => $handler,
    ];
}

function statelessGet(
    string $path,
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
}

describe('routes without the session middleware', function (): void {
    it('sends no session cookie from a route without the session middleware', function (): void {
        ['router' => $router] = statelessRouteHarness();

        $response = $router->handle(statelessGet('/api/health'));

        expect($response->body())->toBe('{"status":"ok"}')
            ->and($response->cookies())->toBeEmpty();
    });

    it('does not touch the session handler on a route without the session middleware', function (): void {
        ['router' => $router, 'handler' => $handler] = statelessRouteHarness();

        $router->handle(statelessGet('/api/health'));

        expect($handler->calls)->toBeEmpty();
    });

    it('throws SessionNotStartedException when a stateless route reads the session', function (): void {
        ['router' => $router, 'handler' => $handler] = statelessRouteHarness();

        expect(fn () => $router->handle(statelessGet('/api/whoami')))->toThrow(SessionNotStartedException::class)
            ->and($handler->calls)->toBeEmpty();
    });

    it('still starts the session and sets the cookie on routes that keep the middleware', function (): void {
        ['router' => $router, 'handler' => $handler] = statelessRouteHarness();

        $response = $router->handle(statelessGet('/dashboard'));

        expect($response->body())->toBe('dashboard')
            ->and($response->cookies())->toHaveCount(1)
            ->and(array_values($response->cookies())[0]->name())->toBe('marko_session')
            ->and($handler->calls)->toContain('write');
    });
});
