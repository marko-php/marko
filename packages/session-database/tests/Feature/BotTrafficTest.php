<?php

declare(strict_types=1);

namespace Marko\Session\Database\Tests\Feature;

use Marko\Core\Container\Container;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Database\Handler\DatabaseSessionHandler;
use Marko\Session\Middleware\SessionMiddleware;
use Marko\Session\Session;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Records every statement the database session handler sends.
 */
class StatementRecordingConnection implements ConnectionInterface
{
    /** @var array<int, string> */
    public array $queries = [];

    /** @var array<int, string> */
    public array $executed = [];

    /**
     * Stored sessions the connection answers for, keyed by id.
     *
     * @var array<string, string>
     */
    public array $storedPayloads = [];

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        $this->queries[] = $sql;
        $id = $bindings[0] ?? null;

        if (
            !is_string($id)
            || !isset($this->storedPayloads[$id])
        ) {
            return [];
        }

        return str_contains($sql, 'SELECT 1')
            ? [['1' => 1]]
            : [['payload' => $this->storedPayloads[$id]]];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->executed[] = $sql;

        return 1;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 0;
    }

    public function driverName(): string
    {
        return 'pgsql';
    }

    public function supportsReturning(): bool
    {
        return true;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    /**
     * @return array<int, string>
     */
    public function sessionWrites(): array
    {
        return array_values(array_filter(
            $this->executed,
            fn (string $sql): bool => str_contains($sql, 'INSERT INTO "sessions"')
                || str_contains(
                    $sql,
                    'UPDATE "sessions"',
                ),
        ));
    }
}

readonly class BotTrafficController
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    /** @noinspection PhpUnused - Invoked by the router */
    public function home(): Response
    {
        return new Response('home');
    }

    /** @noinspection PhpUnused - Invoked by the router */
    public function cart(): Response
    {
        $this->session->set('cart', ['sku-1']);

        return new Response('cart');
    }
}

/**
 * @return array{router: Router, connection: StatementRecordingConnection}
 */
function botTrafficHarness(): array
{
    $connection = new StatementRecordingConnection();
    $config = new SessionConfig(new FakeConfigRepository([
        'session.driver' => 'database',
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
    $clock = new FakeClock();
    $session = new Session(new DatabaseSessionHandler($connection, $config, $clock), $config);

    $container = new Container();
    $container->instance(SessionConfig::class, $config);
    $container->instance(SessionInterface::class, $session);
    $container->instance(ClockInterface::class, $clock);
    $container->instance(BotTrafficController::class, new BotTrafficController($session));

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/',
        controller: BotTrafficController::class,
        action: 'home',
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/cart',
        controller: BotTrafficController::class,
        action: 'cart',
    ));

    return [
        'router' => new Router(new RouteMatcher($routes), $container, [SessionMiddleware::class]),
        'connection' => $connection,
    ];
}

function botGet(
    string $path,
): Request {
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
}

it('performs no session write and sets no session cookie for a 404', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();

    $response = $router->handle(botGet('/wp-login.php'));

    expect($response->statusCode())->toBe(404)
        ->and($response->cookies())->toBeEmpty()
        ->and($connection->executed)->toBeEmpty()
        ->and($connection->queries)->toBeEmpty();
});

it('creates no session for a matched route that never touches it', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();

    $response = $router->handle(botGet('/'));

    expect($response->body())->toBe('home')
        ->and($response->cookies())->toBeEmpty()
        ->and($connection->sessionWrites())->toBeEmpty();
});

it('sends no database query for a cookieless matched route that never touches the session', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();

    $router->handle(botGet('/'));

    expect($connection->queries)->toBeEmpty()
        ->and($connection->executed)->toBeEmpty();
})->issue(267);

it('writes the session and sets the cookie for a matched route that stores a value', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();

    $response = $router->handle(botGet('/cart'));

    expect($response->body())->toBe('cart')
        ->and($response->cookies())->toHaveCount(1)
        ->and($response->cookies()[0]->name())->toBe('marko_session')
        ->and($connection->sessionWrites())->toHaveCount(1);
});

function botGetWithCookie(
    string $path,
    string $sessionId,
): Request {
    return new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path],
        cookies: ['marko_session' => $sessionId],
    );
}

it('creates no session row when an unknown cookie is replayed repeatedly', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();
    $unknownId = str_repeat('a', 40);

    $responses = [];

    for ($i = 0; $i < 5; $i++) {
        $responses[] = $router->handle(botGetWithCookie('/', $unknownId));
    }

    expect($connection->sessionWrites())->toBeEmpty()
        ->and($connection->executed)->toBeEmpty()
        ->and(array_map(
            fn (Response $response): string => $response->cookies()[0]->value(),
            $responses,
        ))->toBe(['', '', '', '', '']);
})->issue(266);

it('refreshes last activity without rewriting the payload for a resumed unmodified session', function (): void {
    ['router' => $router, 'connection' => $connection] = botTrafficHarness();
    $knownId = str_repeat('b', 40);
    $connection->storedPayloads[$knownId] = 'cart|a:1:{i:0;s:5:"sku-1";}';

    $response = $router->handle(botGetWithCookie('/', $knownId));

    expect($response->cookies())->toBeEmpty()
        ->and($connection->executed)->toHaveCount(1)
        ->and($connection->executed[0])->toStartWith('UPDATE "sessions" SET last_activity = ?')
        ->and($connection->executed[0])->not->toContain('payload');
})->issue(266);
