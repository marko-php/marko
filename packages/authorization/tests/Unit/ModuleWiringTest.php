<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Gate;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use RuntimeException;

class WiringController
{
    /** @noinspection PhpUnused - Invoked via the middleware */
    #[Can('edit')]
    public function edit(): Response
    {
        return new Response(body: 'edited');
    }
}

/**
 * @return array<string, mixed>
 */
function authorizationModule(): array
{
    return require dirname(__DIR__, 2) . '/module.php';
}

/**
 * @param array<string, mixed> $module
 */
function manifestFromModule(
    string $name,
    array $module,
): ModuleManifest {
    return new ModuleManifest(
        name: $name,
        version: '1.0.0',
        after: $module['sequence']['after'] ?? [],
        before: $module['sequence']['before'] ?? [],
        globalMiddleware: $module['globalMiddleware'] ?? [],
    );
}

it('registers AuthorizationMiddleware as global middleware', function (): void {
    expect(authorizationModule()['globalMiddleware'])->toBe([AuthorizationMiddleware::class]);
});

it('sequences after the session driver modules', function (): void {
    expect(authorizationModule()['sequence']['after'])
        ->toContain('marko/session-file')
        ->toContain('marko/session-database');
});

it('orders the session middleware before the authorization middleware', function (string $sessionDriver): void {
    $sessionModule = require dirname(__DIR__, 3) . "/$sessionDriver/module.php";

    // Authorization is listed first, so only its sequence hint can produce the expected order.
    $modules = new DependencyResolver()->resolve([
        manifestFromModule('marko/authorization', authorizationModule()),
        manifestFromModule("marko/$sessionDriver", $sessionModule),
    ]);

    expect(new GlobalMiddlewareResolver()->resolve($modules))
        ->toBe([...$sessionModule['globalMiddleware'], AuthorizationMiddleware::class]);
})->with(['session-file', 'session-database']);

it('registers AuthorizationMiddleware as a singleton', function (): void {
    expect(authorizationModule()['singletons'])->toContain(AuthorizationMiddleware::class);
});

it('resolves the gate and runs the middleware with the shipped config', function (): void {
    $defaultGuard = new FakeGuard(name: 'web');
    $defaultGuard->setUser(new FakeAuthenticatable());

    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    $authManager = new class ($defaultGuard) extends AuthManager
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly GuardInterface $defaultGuard,
        ) {}

        public function guard(
            ?string $name = null,
        ): GuardInterface {
            if ($name !== null) {
                throw new RuntimeException("Expected the authentication default guard, got '$name'");
            }

            return $this->defaultGuard;
        }
    };

    $module = authorizationModule();
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));
    $container->instance(EventDispatcherInterface::class, new FakeEventDispatcher());
    $container->instance(AuthManager::class, $authManager);
    $container->instance(ConfigRepositoryInterface::class, new ConfigRepository([
        'authorization' => require dirname(__DIR__, 2) . '/config/authorization.php',
    ]));

    foreach ($module['bindings'] as $id => $factory) {
        $container->bind($id, $factory);
    }

    foreach ($module['singletons'] as $id) {
        $container->singleton($id);
    }

    /** @var GateInterface $gate */
    $gate = $container->get(GateInterface::class);
    $gate->define('edit', fn (): bool => true);

    /** @var AuthorizationMiddleware $middleware */
    $middleware = $container->get(AuthorizationMiddleware::class);
    $request = new Request()->withRoute(WiringController::class, 'edit');

    $response = $middleware->handle($request, fn (Request $r): Response => new Response(body: 'edited'));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('edited');
});

it('builds the middleware with the guard configured for authorization', function (): void {
    $defaultGuard = new FakeGuard(name: 'web');
    $defaultGuard->setUser(new FakeAuthenticatable());
    $apiGuard = new FakeGuard(name: 'api');

    /** @noinspection PhpMissingParentConstructorInspection - Test stub intentionally skips parent */
    $authManager = new class ($defaultGuard, $apiGuard) extends AuthManager
    {
        /** @noinspection PhpMissingParentConstructorInspection */
        public function __construct(
            private readonly GuardInterface $defaultGuard,
            private readonly GuardInterface $apiGuard,
        ) {}

        public function guard(
            ?string $name = null,
        ): GuardInterface {
            return $name === 'api' ? $this->apiGuard : $this->defaultGuard;
        }
    };

    $gate = new Gate(
        guard: $apiGuard,
        policyRegistry: new PolicyRegistry(),
        gateHookRegistry: GateHooks::registry(),
        eventDispatcher: new FakeEventDispatcher(),
    );
    $gate->define('edit', fn (): bool => true);

    $container = new Container();
    $container->instance(AuthManager::class, $authManager);
    $container->instance(GateInterface::class, $gate);
    $container->instance(
        AuthorizationConfig::class,
        new AuthorizationConfig(new FakeConfigRepository(['authorization.default_guard' => 'api'])),
    );

    $middleware = authorizationModule()['bindings'][AuthorizationMiddleware::class]($container);
    $request = new Request()->withRoute(WiringController::class, 'edit');

    // The default guard is logged in but the authorization guard ('api') is not, so 401 proves which guard is used.
    expect($middleware)->toBeInstanceOf(AuthorizationMiddleware::class)
        ->and(fn () => $middleware->handle($request, fn (Request $r): Response => new Response(body: 'edited')))
        ->toThrow(HttpException::class, 'Unauthorized.');
});
