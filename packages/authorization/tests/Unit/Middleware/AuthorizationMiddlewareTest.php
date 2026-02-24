<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit\Middleware;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authorization\Attributes\Can;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Gate;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

// Test controllers
class PostController
{
    #[Can('create-post')]
    public function create(): Response
    {
        return new Response(body: 'created', statusCode: 200);
    }

    public function index(): Response
    {
        return new Response(body: 'index', statusCode: 200);
    }

    #[Can('update', 'App\\Entity\\Post')]
    public function update(): Response
    {
        return new Response(body: 'updated', statusCode: 200);
    }
}

// Guard stub for middleware tests
class MiddlewareStubGuard implements GuardInterface
{
    private ?AuthenticatableInterface $authenticatedUser = null;

    public ?UserProviderInterface $provider = null {
        set {
            $this->provider = $value;
        }
    }

    public function setUser(
        ?AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function check(): bool
    {
        return $this->authenticatedUser !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->authenticatedUser;
    }

    public function id(): int|string|null
    {
        return $this->authenticatedUser?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        return false;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        $this->authenticatedUser = $user;
    }

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void
    {
        $this->authenticatedUser = null;
    }

    public function getName(): string
    {
        return 'middleware-test';
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

function createMiddlewareGate(
    ?GuardInterface $guard = null,
): Gate {
    return new Gate(
        guard: $guard ?? new MiddlewareStubGuard(),
        policyRegistry: new PolicyRegistry(),
    );
}

function createAuthMiddleware(
    GateInterface $gate,
    GuardInterface $guard,
    ?string $controller = null,
    ?string $action = null,
): AuthorizationMiddleware {
    return new AuthorizationMiddleware(
        gate: $gate,
        guard: $guard,
        controller: $controller,
        action: $action,
    );
}

function createSuccessfulNext(): callable
{
    return fn (Request $r): Response => new Response(body: 'success', statusCode: 200);
}

it('allows request when gate allows the ability', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('returns 403 when gate denies the ability', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => false);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(403)
        ->and($response->body())->toBe('Forbidden');
});

it('returns JSON 403 for API requests when denied', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => false);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request(server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(403)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and(json_decode($response->body(), true))->toBe(['error' => 'Forbidden']);
});

it('skips authorization when no Can attribute is present', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'index',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toBe('success');
});

it('reads Can attribute from controller method via reflection', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);
    // Define the ability that matches the #[Can('create-post')] attribute
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    // If attribute was read correctly, the gate allows it
    expect($response->statusCode())->toBe(200);
});

it('returns 401 when user is not authenticated', function (): void {
    $guard = new MiddlewareStubGuard(); // No user set

    $gate = createMiddlewareGate(guard: $guard);
    $gate->define('create-post', fn (?AuthorizableInterface $user): bool => true);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request(server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(401);
});

it('returns plain 401 for web requests when not authenticated', function (): void {
    $guard = new MiddlewareStubGuard(); // No user set

    $gate = createMiddlewareGate(guard: $guard);

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'create',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(401)
        ->and($response->body())->toBe('Unauthorized');
});

it('passes entity class from Can attribute to gate', function (): void {
    $guard = new MiddlewareStubGuard();
    $guard->setUser(new MiddlewareStubUser());

    $gate = createMiddlewareGate(guard: $guard);

    // Define the ability that will receive the entity class as argument
    $receivedArgs = [];
    $gate->define('update', function (?AuthorizableInterface $user, mixed ...$args) use (&$receivedArgs): bool {
        $receivedArgs = $args;

        return true;
    });

    $middleware = createAuthMiddleware(
        gate: $gate,
        guard: $guard,
        controller: PostController::class,
        action: 'update',
    );

    $request = new Request();
    $response = $middleware->handle($request, createSuccessfulNext());

    expect($response->statusCode())->toBe(200)
        ->and($receivedArgs)->toBe(['App\\Entity\\Post']);
});
