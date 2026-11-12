<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Feature;

use Closure;
use DateTimeImmutable;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Gate;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;

class RouterPostController
{
    /** @noinspection PhpUnused - Invoked via the router */
    #[Can('edit')]
    public function edit(): Response
    {
        return new Response(body: 'edited');
    }

    /** @noinspection PhpUnused - Invoked via the router */
    public function index(): Response
    {
        return new Response(body: 'index');
    }
}

#[Can('admin.access')]
class RouterAdminController
{
    /** @noinspection PhpUnused - Invoked via the router */
    public function dashboard(): Response
    {
        return new Response(body: 'dashboard');
    }

    /** @noinspection PhpUnused - Invoked via the router */
    #[Can('admin.reports')]
    public function reports(): Response
    {
        return new Response(body: 'reports');
    }
}

readonly class RouterGateController
{
    public function __construct(
        private GateInterface $gate,
    ) {}

    /**
     * Imperative check with no #[Can]: the denial comes from Gate::authorize().
     *
     * @noinspection PhpUnused - Invoked via the router
     */
    public function publish(): Response
    {
        $this->gate->authorize('publish-secret-report', RouterSecretReport::class);

        return new Response(body: 'published');
    }
}

class RouterSecretReport {}

class RouterStubUser implements AuthorizableInterface
{
    public function getAuthIdentifier(): int
    {
        return 1;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return 'hashed';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return false;
    }
}

/**
 * Build a real Router with AuthorizationMiddleware registered as global
 * middleware, resolved by the real container exactly as in production.
 *
 * @param array<string, bool> $abilities
 */
function createAuthorizedRouter(
    array $abilities,
    bool $authenticated = true,
): Router {
    return new Router(
        matcher: new RouteMatcher(createRouterRoutes()),
        container: createAuthorizedContainer($abilities, $authenticated),
        globalMiddleware: [AuthorizationMiddleware::class],
    );
}

/**
 * @param array<string, bool> $abilities
 */
function createAuthorizedContainer(
    array $abilities,
    bool $authenticated,
): Container {
    $guard = new FakeGuard(name: 'web', attemptResult: false);

    if ($authenticated) {
        $guard->setUser(new RouterStubUser());
    }

    $gate = new Gate(
        guard: $guard,
        policyRegistry: new PolicyRegistry(),
        gateHookRegistry: GateHooks::registry(),
        eventDispatcher: new FakeEventDispatcher(),
    );

    foreach ($abilities as $ability => $allowed) {
        $gate->define($ability, fn (?AuthorizableInterface $user): bool => $allowed);
    }

    $container = new Container();
    $container->instance(GuardInterface::class, $guard);
    $container->instance(GateInterface::class, $gate);
    $container->bind(
        AuthorizationMiddleware::class,
        fn (): AuthorizationMiddleware => new AuthorizationMiddleware(
            gate: fn (): GateInterface => $gate,
            guard: fn (): GuardInterface => $guard,
        ),
    );

    return $container;
}

function createRouterRoutes(): RouteCollection
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/posts/edit',
        controller: RouterPostController::class,
        action: 'edit',
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/posts',
        controller: RouterPostController::class,
        action: 'index',
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/posts/publish',
        controller: RouterGateController::class,
        action: 'publish',
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin',
        controller: RouterAdminController::class,
        action: 'dashboard',
    ));
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin/reports',
        controller: RouterAdminController::class,
        action: 'reports',
    ));

    return $routes;
}

/**
 * Build a Router whose container holds only marko/authorization's own module
 * bindings: no AuthManager, guard, session or authentication config.
 */
function createUnconfiguredRouter(): Router
{
    /** @var array{bindings: array<string, string|Closure>, singletons: list<string>, arguments: array<string, array<string, mixed>>} $module */
    $module = require dirname(__DIR__, 2) . '/module.php';
    $container = new Container();

    new BindingRegistry($container)->registerModule(new ModuleManifest(
        name: 'marko/authorization',
        version: '1.0.0',
        bindings: $module['bindings'],
        singletons: $module['singletons'],
        arguments: $module['arguments'] ?? [],
    ));

    return new Router(
        matcher: new RouteMatcher(createRouterRoutes()),
        container: $container,
        globalMiddleware: [AuthorizationMiddleware::class],
    );
}

/**
 * @param array<string, string> $headers
 */
function createRouterRequest(
    string $path,
    array $headers = [],
): Request {
    return new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        ...$headers,
    ]);
}

it('returns 403 through the router when the gate denies a Can ability', function (): void {
    $router = createAuthorizedRouter(abilities: ['edit' => false]);

    $response = $router->handle(createRouterRequest('/posts/edit'));

    expect($response->statusCode())->toBe(403)
        ->and($response->headers()['Content-Type'])->toContain('text/html')
        ->and($response->body())->toContain('403 Forbidden')
        ->toContain('Forbidden.');
});

it('returns 200 through the router when the gate allows a Can ability', function (): void {
    $router = createAuthorizedRouter(abilities: ['edit' => true]);

    $response = $router->handle(createRouterRequest('/posts/edit'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('edited');
});

it('returns an HTML 401 through the renderer for a guest without a JSON Accept header', function (): void {
    $router = createAuthorizedRouter(abilities: ['edit' => true], authenticated: false);

    $response = $router->handle(createRouterRequest('/posts/edit', ['HTTP_ACCEPT' => 'text/html']));

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toContain('text/html')
        ->and($response->body())->toContain('401 Unauthorized')
        ->and($response->body())->not->toContain('edited');
});

it('returns a JSON 401 through the renderer for a guest asking for application/vnd.api+json', function (): void {
    $router = createAuthorizedRouter(abilities: ['edit' => true], authenticated: false);

    $response = $router->handle(createRouterRequest('/posts/edit', ['HTTP_ACCEPT' => 'application/vnd.api+json']));

    expect($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toContain('application/json')
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Unauthorized.']);
});

it('passes routes without Can through the global middleware untouched', function (): void {
    $router = createAuthorizedRouter(abilities: [], authenticated: false);

    $response = $router->handle(createRouterRequest('/posts'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('index');
});

it('applies a class-level Can to every action', function (): void {
    $denied = createAuthorizedRouter(abilities: ['admin.access' => false]);
    $allowed = createAuthorizedRouter(abilities: ['admin.access' => true]);

    expect($denied->handle(createRouterRequest('/admin'))->statusCode())->toBe(403)
        ->and($allowed->handle(createRouterRequest('/admin'))->body())->toBe('dashboard');
});

it('lets a method-level Can override the class-level Can', function (): void {
    $router = createAuthorizedRouter(abilities: ['admin.access' => false, 'admin.reports' => true]);

    $response = $router->handle(createRouterRequest('/admin/reports'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('reports');
});

it('returns 403, not 500, when a controller calls Gate::authorize() for a denied ability', function (): void {
    $router = createAuthorizedRouter(abilities: ['publish-secret-report' => false]);

    $response = $router->handle(createRouterRequest('/posts/publish', ['HTTP_ACCEPT' => 'application/json']));

    expect($response->statusCode())->toBe(403)
        ->and(json_decode($response->body(), true))->toBe(['message' => 'Forbidden.']);
});

it('never includes the ability or resource name in the 403 body', function (): void {
    $router = createAuthorizedRouter(abilities: ['publish-secret-report' => false]);

    $json = $router->handle(createRouterRequest('/posts/publish', ['HTTP_ACCEPT' => 'application/json']));
    $html = $router->handle(createRouterRequest('/posts/publish', ['HTTP_ACCEPT' => 'text/html']));

    expect($json->body())->not->toContain('publish-secret-report')
        ->not->toContain('RouterSecretReport')
        ->and($html->statusCode())->toBe(403)
        ->and($html->body())->not->toContain('publish-secret-report')
        ->not->toContain('RouterSecretReport')
        ->not->toContain('published');
});

it('lets the controller run when Gate::authorize() allows the ability', function (): void {
    $router = createAuthorizedRouter(abilities: ['publish-secret-report' => true]);

    $response = $router->handle(createRouterRequest('/posts/publish'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('published');
});

it('returns 200 for a route without Can when no auth or session is configured', function (): void {
    $response = createUnconfiguredRouter()->handle(createRouterRequest('/posts'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('index');
});

it('returns 404 for an unmatched request when no auth or session is configured', function (): void {
    $response = createUnconfiguredRouter()->handle(createRouterRequest('/missing'));

    expect($response->statusCode())->toBe(404);
});

it('fails loudly on a Can route when no auth or session is configured', function (): void {
    $router = createUnconfiguredRouter();

    expect(fn () => $router->handle(createRouterRequest('/posts/edit')))
        ->toThrow(BindingException::class);
});
