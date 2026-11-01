<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Feature;

use DateTimeImmutable;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Guard\TokenGuard;
use Marko\AuthenticationToken\Middleware\TokenRequestMiddleware;
use Marko\AuthenticationToken\Service\TokenManager;
use Marko\AuthenticationToken\Tests\Fixtures\InMemoryTokenRepository;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;
use Psr\Clock\ClockInterface;

class TokenWiringController
{
    /** @noinspection PhpUnused - Invoked via the router */
    public function profile(): Response
    {
        return new Response(body: 'profile');
    }

    /** @noinspection PhpUnused - Invoked via the router */
    #[Can('reports.view')]
    public function reports(): Response
    {
        return new Response(body: 'reports');
    }
}

/**
 * @return array<string, mixed>
 */
function tokenModule(): array
{
    return require dirname(__DIR__, 2) . '/module.php';
}

/**
 * @param array<string, mixed> $module
 */
function registerModule(
    Container $container,
    array $module,
): void {
    foreach ($module['bindings'] ?? [] as $id => $binding) {
        $container->bind($id, $binding);
    }

    foreach ($module['singletons'] ?? [] as $key => $value) {
        if (is_string($key)) {
            $container->bind($key, $value);
            $container->singleton($key);
        } else {
            $container->singleton($value);
        }
    }
}

/**
 * A container wired by the real authentication, authentication-token and
 * authorization module.php files, booted the way Application boots it.
 */
function bootTokenContainer(
    InMemoryTokenRepository $tokenRepository,
    string $now = '2026-01-01 12:00:00',
    string $databaseTimezone = 'UTC',
): Container {
    $packages = dirname(__DIR__, 3);
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'authentication.default.guard' => 'api',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
        'authentication.remember.cookie.prefix' => 'remember_',
        'authentication.remember.lifetime' => 60,
        'authentication.throttle.enabled' => false,
        'authorization.default_guard' => null,
        'authentication-token.token_expiration_days' => (require "$packages/authentication-token/config/authentication-token.php")['token_expiration_days'],
    ]));
    $session = new FakeSession();
    $session->start();
    $container->instance(SessionInterface::class, $session);
    $container->instance(UserProviderInterface::class, new FakeUserProvider([1 => new FakeAuthenticatable(id: 1)]));
    $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
    $container->instance(ClockInterface::class, new FakeClock($now));
    $container->instance(DatabaseTimezoneConfig::class, DatabaseTimezoneConfig::fromName($databaseTimezone));
    $container->instance(TokenRepositoryInterface::class, $tokenRepository);
    // No modules to scan: the authorization Gate runs no gate hooks.
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));

    registerModule($container, require "$packages/authentication/module.php");
    registerModule($container, tokenModule());
    registerModule($container, require "$packages/authorization/module.php");

    $container->call(tokenModule()['boot']);

    return $container;
}

function tokenRouter(
    Container $container,
): Router {
    $container->instance(AuthMiddleware::class, new AuthMiddleware(
        auth: $container->get(AuthManager::class),
        guard: 'api',
    ));

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/profile',
        controller: TokenWiringController::class,
        action: 'profile',
        middleware: [AuthMiddleware::class],
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/reports',
        controller: TokenWiringController::class,
        action: 'reports',
    ));

    return new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: [TokenRequestMiddleware::class, AuthorizationMiddleware::class],
    );
}

function bearerRequest(
    string $path,
    string $token,
): Request {
    return new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_AUTHORIZATION' => "Bearer $token",
    ]);
}

function issueToken(
    InMemoryTokenRepository $tokenRepository,
    ?string $expiresAt = null,
): string {
    return bootTokenContainer($tokenRepository)->get(TokenManager::class)->createToken(
        user: new FakeAuthenticatable(id: 1),
        name: 'cli',
        expiresAt: $expiresAt !== null ? new DateTimeImmutable($expiresAt) : null,
    )->plainTextToken;
}

it('no longer declares the unused guards module key', function (): void {
    expect(tokenModule())->not->toHaveKey('guards');
});

it('makes AuthManager return the authentication-token TokenGuard for the token driver', function (): void {
    $container = bootTokenContainer(new InMemoryTokenRepository());

    $guard = $container->get(AuthManager::class)->guard('api');

    expect($guard)->toBeInstanceOf(TokenGuard::class)
        ->and($guard->getName())->toBe('api');
});

it('gives the container-resolved AuthManager the singleton registry', function (): void {
    $container = bootTokenContainer(new InMemoryTokenRepository());

    expect($container->get(GuardDriverRegistry::class)->has('token'))->toBeTrue()
        ->and($container->get(AuthManager::class)->guard('api'))->toBeInstanceOf(TokenGuard::class);
});

it('builds the token guard with the database timezone config', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = bootTokenContainer($tokenRepository, databaseTimezone: 'Asia/Tokyo')->get(
        TokenManager::class,
    )->createToken(
        user: new FakeAuthenticatable(id: 1),
        name: 'cli',
        expiresAt: new DateTimeImmutable('2026-01-01T12:30:00Z'),
    )->plainTextToken;

    $beforeExpiry = tokenRouter(bootTokenContainer($tokenRepository, databaseTimezone: 'Asia/Tokyo'))
        ->handle(bearerRequest('/profile', $token));
    $afterExpiry = tokenRouter(bootTokenContainer($tokenRepository, '2026-01-01 12:31:00', 'Asia/Tokyo'))
        ->handle(bearerRequest('/profile', $token));

    expect($tokenRepository->findByToken(hash('sha256', $token))?->expiresAt)->toBe('2026-01-01 21:30:00')
        ->and($beforeExpiry->statusCode())->toBe(200)
        ->and($afterExpiry->statusCode())->toBe(401);
});

it('lets a valid token through AuthMiddleware', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository, expiresAt: '2026-01-01 13:00:00');

    $response = tokenRouter(bootTokenContainer($tokenRepository))->handle(bearerRequest('/profile', $token));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('profile');
});

it('returns 401 from AuthMiddleware for an expired token', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository, expiresAt: '2026-01-01 11:00:00');

    $response = tokenRouter(bootTokenContainer($tokenRepository))->handle(bearerRequest('/profile', $token));

    expect($response->statusCode())->toBe(401)
        ->and($response->body())->not->toContain('profile');
});

it('sends WWW-Authenticate: Bearer on the AuthMiddleware 401 for an expired token', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository, expiresAt: '2026-01-01 11:00:00');

    $response = tokenRouter(bootTokenContainer($tokenRepository))->handle(bearerRequest('/profile', $token));

    expect($response->headers()['WWW-Authenticate'] ?? null)->toBe('Bearer');
});

it('returns 401 from the #[Can] authorization middleware for an expired token', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository, expiresAt: '2026-01-01 11:00:00');

    $response = tokenRouter(bootTokenContainer($tokenRepository))->handle(bearerRequest('/reports', $token));

    expect($response->statusCode())->toBe(401)
        ->and($response->body())->not->toContain('reports');
});

it('reaches the #[Can] gate check with a valid token', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository);

    // Authenticated, but no 'reports.view' ability is defined: the gate denies with 403, not 401.
    $response = tokenRouter(bootTokenContainer($tokenRepository))->handle(bearerRequest('/reports', $token));

    expect($response->statusCode())->toBe(403);
});

it('accepts a token issued without an expiry until the shipped default lifetime ends', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository);

    $container = bootTokenContainer($tokenRepository, now: '2027-01-01 11:59:59');
    $response = tokenRouter($container)->handle(bearerRequest('/profile', $token));

    expect($response->statusCode())->toBe(200);
});

it('rejects a token issued without an expiry once the shipped default lifetime has passed', function (): void {
    $tokenRepository = new InMemoryTokenRepository();
    $token = issueToken($tokenRepository);

    $container = bootTokenContainer($tokenRepository, now: '2027-01-01 12:00:01');
    $response = tokenRouter($container)->handle(bearerRequest('/profile', $token));

    expect($response->statusCode())->toBe(401);
});

it('orders TokenRequestMiddleware before AuthorizationMiddleware in the global middleware', function (): void {
    $manifest = fn (string $name, array $module): ModuleManifest => new ModuleManifest(
        name: $name,
        version: '1.0.0',
        after: $module['sequence']['after'] ?? [],
        before: $module['sequence']['before'] ?? [],
        globalMiddleware: $module['globalMiddleware'] ?? [],
    );
    $authorizationModule = require dirname(__DIR__, 3) . '/authorization/module.php';

    // Authorization is listed first, so only the token module's sequence hint can produce the expected order.
    $modules = new DependencyResolver()->resolve([
        $manifest('marko/authorization', $authorizationModule),
        $manifest('marko/authentication-token', tokenModule()),
    ]);

    expect(new GlobalMiddlewareResolver()->resolve($modules))
        ->toBe([TokenRequestMiddleware::class, AuthorizationMiddleware::class]);
});
