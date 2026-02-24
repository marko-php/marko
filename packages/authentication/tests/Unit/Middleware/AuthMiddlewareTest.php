<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Authentication\Tests\Integration\TestSession;
use Marko\Authentication\Tests\Integration\TestUser;
use Marko\Authentication\Tests\Integration\TestUserProvider;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

// Test helper for config repository
readonly class MiddlewareTestConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private array $values = [],
    ) {}

    public function get(
        string $key,
        ?string $scope = null,
    ): mixed {
        if (!$this->has($key, $scope)) {
            throw new ConfigNotFoundException($key);
        }

        return $this->values[$key];
    }

    public function getString(
        string $key,
        ?string $scope = null,
    ): string {
        return (string) $this->get($key, $scope);
    }

    public function getInt(
        string $key,
        ?string $scope = null,
    ): int {
        return (int) $this->get($key, $scope);
    }

    public function getBool(
        string $key,
        ?string $scope = null,
    ): bool {
        return (bool) $this->get($key, $scope);
    }

    public function getFloat(
        string $key,
        ?string $scope = null,
    ): float {
        return (float) $this->get($key, $scope);
    }

    public function getArray(
        string $key,
        ?string $scope = null,
    ): array {
        return (array) $this->get($key, $scope);
    }

    public function has(
        string $key,
        ?string $scope = null,
    ): bool {
        return isset($this->values[$key]);
    }

    public function all(
        ?string $scope = null,
    ): array {
        return $this->values;
    }

    public function withScope(
        string $scope,
    ): ConfigRepositoryInterface {
        return $this;
    }
}

// Helper function to create AuthManager with authenticated user
function createAuthManagerWithUser(
    ?TestUser $user = null,
): AuthManager {
    $configRepo = new MiddlewareTestConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new TestSession();
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: $user !== null,
    );

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // If user provided, authenticate them
    if ($user !== null) {
        $manager->attempt(['email' => 'test@example.com', 'password' => 'password']);
    }

    return $manager;
}

test('it allows authenticated users through', function (): void {
    $user = new TestUser(id: 1);
    $authManager = createAuthManagerWithUser($user);

    $middleware = new AuthMiddleware($authManager);

    $request = new Request();
    $expectedResponse = new Response(body: 'success', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});

test('it blocks unauthenticated users', function (): void {
    $authManager = createAuthManagerWithUser(); // No user

    $middleware = new AuthMiddleware($authManager);

    $request = new Request();
    $nextCalled = false;

    $response = $middleware->handle(
        $request,
        function () use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response(body: 'success', statusCode: 200);
        },
    );

    expect($nextCalled)->toBeFalse()
        ->and($response->statusCode())->not->toBe(200);
});

test('it returns 401 for API guard when unauthenticated', function (): void {
    $authManager = createAuthManagerWithUser();

    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'api',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    expect($response->statusCode())->toBe(401)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json');
});

test('it redirects for web guard when unauthenticated', function (): void {
    $authManager = createAuthManagerWithUser();

    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'web',
        redirectTo: '/login',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    expect($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/login');
});

test('it supports specifying guard via parameter', function (): void {
    $user = new TestUser(id: 1);
    $configRepo = new MiddlewareTestConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new TestSession();
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
    );

    $authManager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // Authenticate on web guard
    $authManager->attempt(['email' => 'test@example.com', 'password' => 'password']);

    // Middleware using 'api' guard should fail (user not authenticated on api guard)
    $middleware = new AuthMiddleware(
        auth: $authManager,
        guard: 'api',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'success', statusCode: 200),
    );

    // API guard returns 401 JSON because user is not authenticated on API guard
    expect($response->statusCode())->toBe(401);
});

test('it uses default guard when not specified', function (): void {
    $user = new TestUser(id: 1);
    $authManager = createAuthManagerWithUser($user);

    // Middleware without guard parameter uses default (web)
    $middleware = new AuthMiddleware(
        auth: $authManager,
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'success', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    // User is authenticated on default guard, so request passes through
    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});
