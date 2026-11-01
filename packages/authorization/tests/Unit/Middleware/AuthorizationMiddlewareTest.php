<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Middleware;

use DateTimeImmutable;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Gate;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;
use RuntimeException;

// Test controllers
class PostController
{
    /** @noinspection PhpUnused - Read via reflection by the middleware */
    #[Can('create-post')]
    public function create(): Response
    {
        return new Response(body: 'created', statusCode: 200);
    }

    /** @noinspection PhpUnused - Read via reflection by the middleware */
    public function index(): Response
    {
        return new Response(body: 'index', statusCode: 200);
    }

    /** @noinspection PhpUnused - Read via reflection by the middleware */
    #[Can('update', 'App\\Entity\\Post')]
    public function update(): Response
    {
        return new Response(body: 'updated', statusCode: 200);
    }
}

// Stub AuthorizableInterface user for middleware tests
class MiddlewareStubUser implements AuthorizableInterface
{
    public function __construct(
        private readonly int $id = 1,
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
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

// Stands in for a token guard: stateless, with a Bearer challenge
class MiddlewareStatelessGuard extends FakeGuard implements StatelessGuardInterface
{
    public function getChallenge(): string
    {
        return 'Bearer';
    }
}

function createMiddlewareGate(
    ?FakeGuard $guard = null,
): Gate {
    return new Gate(
        guard: $guard ?? new FakeGuard(name: 'middleware-test', attemptResult: false),
        policyRegistry: new PolicyRegistry(),
        gateHookRegistry: GateHooks::registry(),
        eventDispatcher: new FakeEventDispatcher(),
    );
}

function createAuthMiddleware(
    GateInterface $gate,
    FakeGuard $guard,
): AuthorizationMiddleware {
    return new AuthorizationMiddleware(
        gate: fn (): GateInterface => $gate,
        guard: fn (): GuardInterface => $guard,
    );
}

/**
 * Counts how often each factory runs. The factories fail the test if a
 * route without #[Can] ever calls them.
 */
class FactorySpy
{
    public int $gateCalls = 0;

    public int $guardCalls = 0;

    public function __construct(
        private readonly GateInterface $gate,
        private readonly GuardInterface $guard,
    ) {}

    public function middleware(): AuthorizationMiddleware
    {
        return new AuthorizationMiddleware(
            gate: function (): GateInterface {
                $this->gateCalls++;

                return $this->gate;
            },
            guard: function (): GuardInterface {
                $this->guardCalls++;

                return $this->guard;
            },
        );
    }
}

/**
 * @param array<string, mixed> $server
 */
function createRoutedRequest(
    string $action,
    array $server = [],
): Request {
    return new Request(server: $server)->withRoute(PostController::class, $action);
}

function createSuccessfulNext(): callable
{
    return fn (Request $r): Response => new Response(body: 'success', statusCode: 200);
}

it('allows request when gate allows the ability', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    $request = createRoutedRequest('create');
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('throws a 403 AuthorizationException when gate denies the ability', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => false);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    expect(fn () => $middleware->handle(createRoutedRequest('create'), createSuccessfulNext()))
        ->toThrow(AuthorizationException::class);
});

it('carries the ability and entity class on the thrown AuthorizationException', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('update', fn (?AuthorizableInterface $user, mixed ...$args): bool => false);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    try {
        $middleware->handle(createRoutedRequest('update'), createSuccessfulNext());
        $this->fail('Expected AuthorizationException');
    } catch (AuthorizationException $exception) {
        expect($exception->getStatusCode())->toBe(403)
            ->and($exception->getAbility())->toBe('update')
            ->and($exception->getResource())->toBe('App\\Entity\\Post');
    }
});

it('skips authorization when no Can attribute is present', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    $request = createRoutedRequest('index');
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('reads Can attribute from controller method via reflection', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    // Define the ability that matches the #[Can('create-post')] attribute
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    $request = createRoutedRequest('create');
    $response = $middleware->handle($request, createSuccessfulNext());

    // If attribute was read correctly, the gate allows it
    expect($response->statusCode())->toBe(200);
});

it('throws a 401 HttpException when user is not authenticated', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false); // No user set

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    try {
        $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());
        $this->fail('Expected HttpException');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(401);
    }
});

it("sends the stateless guard's WWW-Authenticate challenge on a 401 for a guest", function (): void {
    $guard = new MiddlewareStatelessGuard(name: 'api', attemptResult: false); // No user set

    $middleware = createAuthMiddleware(gate: createMiddlewareGate(guard: $guard), guard: $guard);

    try {
        $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());
        $this->fail('Expected UnauthenticatedException');
    } catch (UnauthenticatedException $exception) {
        expect($exception->getStatusCode())->toBe(401)
            ->and($exception->getMessage())->toBe('Unauthorized.')
            ->and($exception->getHeaders())->toBe(['WWW-Authenticate' => 'Bearer']);
    }
});

it('sends no WWW-Authenticate header on a 401 for a guest on a stateful guard', function (): void {
    $guard = new FakeGuard(name: 'web', attemptResult: false); // No user set

    $middleware = createAuthMiddleware(gate: createMiddlewareGate(guard: $guard), guard: $guard);

    try {
        $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());
        $this->fail('Expected UnauthenticatedException');
    } catch (UnauthenticatedException $exception) {
        expect($exception->getStatusCode())->toBe(401)
            ->and($exception->getHeaders())->toBe([]);
    }
});

it('passes entity class from Can attribute to gate', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);

    // Define the ability that will receive the entity class as argument
    $receivedArgs = [];
    $gate->define('update', function (?AuthorizableInterface $user, mixed ...$args) use (&$receivedArgs): bool {
        $receivedArgs = $args;

        return true;
    });

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    $request = createRoutedRequest('update');
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($receivedArgs)->toBe(['App\\Entity\\Post']);
});

it('passes through when the request has no matched route', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false); // No user set

    $middleware = createAuthMiddleware(gate: createMiddlewareGate(guard: $guard), guard: $guard);

    $response = $middleware->handle(new Request(), createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('reuses the resolved Can attribute for repeated requests to the same action', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => false);

    $middleware = createAuthMiddleware(gate: $gate, guard: $guard);

    $denied = 0;

    foreach ([1, 2] as $ignored) {
        try {
            $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());
        } catch (AuthorizationException) {
            $denied++;
        }
    }

    $unprotected = $middleware->handle(createRoutedRequest('index'), createSuccessfulNext());

    expect($denied)->toBe(2)
        ->and($unprotected->statusCode())->toBe(200);
});

it('never calls the gate or guard factory for a route without Can', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $spy = new FactorySpy(gate: createMiddlewareGate(guard: $guard), guard: $guard);
    $middleware = $spy->middleware();

    $response = $middleware->handle(createRoutedRequest('index'), createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($spy->gateCalls)->toBe(0)
        ->and($spy->guardCalls)->toBe(0);
});

it('never calls the gate or guard factory for an unmatched request', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $spy = new FactorySpy(gate: createMiddlewareGate(guard: $guard), guard: $guard);
    $middleware = $spy->middleware();

    $response = $middleware->handle(new Request(), createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($spy->gateCalls)->toBe(0)
        ->and($spy->guardCalls)->toBe(0);
});

it('resolves the gate and guard once across repeated Can requests', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());
    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);
    $spy = new FactorySpy(gate: $gate, guard: $guard);
    $middleware = $spy->middleware();

    $first = $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());
    $second = $middleware->handle(createRoutedRequest('create'), createSuccessfulNext());

    expect($first->statusCode())->toBe(200)
        ->and($second->statusCode())->toBe(200)
        ->and($spy->gateCalls)->toBe(1)
        ->and($spy->guardCalls)->toBe(1);
});

it('never calls the gate factory when the guard reports a guest', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false); // No user set
    $spy = new FactorySpy(gate: createMiddlewareGate(guard: $guard), guard: $guard);
    $middleware = $spy->middleware();

    expect(fn () => $middleware->handle(createRoutedRequest('create'), createSuccessfulNext()))
        ->toThrow(HttpException::class)
        ->and($spy->guardCalls)->toBe(1)
        ->and($spy->gateCalls)->toBe(0);
});

it('retries a factory that threw on the next Can request instead of caching the failure', function (): void {
    $guard = new FakeGuard(name: 'middleware-test', attemptResult: false);
    $guard->setUser(new MiddlewareStubUser());
    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);
    $guardCalls = 0;

    $middleware = new AuthorizationMiddleware(
        gate: fn (): GateInterface => $gate,
        guard: function () use (&$guardCalls, $guard): GuardInterface {
            if (++$guardCalls === 1) {
                throw new RuntimeException('Guard not configured yet');
            }

            return $guard;
        },
    );

    expect(fn () => $middleware->handle(createRoutedRequest('create'), createSuccessfulNext()))
        ->toThrow(RuntimeException::class, 'Guard not configured yet')
        ->and($middleware->handle(createRoutedRequest('create'), createSuccessfulNext())->statusCode())->toBe(200)
        ->and($guardCalls)->toBe(2);
});
