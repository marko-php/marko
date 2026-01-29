<?php

declare(strict_types=1);

use Marko\Auth\AuthManager;
use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Middleware\GuestMiddleware;
use Marko\Auth\Tests\Integration\TestSession;
use Marko\Auth\Tests\Integration\TestUser;
use Marko\Auth\Tests\Integration\TestUserProvider;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

// Test helper for config repository
readonly class GuestMiddlewareTestConfigRepository implements ConfigRepositoryInterface
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
function createGuestAuthManagerWithUser(
    ?TestUser $user = null,
): AuthManager {
    $configRepo = new GuestMiddlewareTestConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
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

test('GuestMiddleware allows unauthenticated users', function (): void {
    $authManager = createGuestAuthManagerWithUser(); // No user

    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'login page', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});

test('GuestMiddleware redirects authenticated users', function (): void {
    $user = new TestUser(id: 1);
    $authManager = createGuestAuthManagerWithUser($user);

    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
    );

    $request = new Request();
    $nextCalled = false;

    $response = $middleware->handle(
        $request,
        function () use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response(body: 'login page', statusCode: 200);
        },
    );

    expect($nextCalled)->toBeFalse()
        ->and($response->statusCode())->toBe(302)
        ->and($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/dashboard');
});

test('it supports configurable redirect URL', function (): void {
    $user = new TestUser(id: 1);
    $authManager = createGuestAuthManagerWithUser($user);

    // Custom redirect URL
    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/custom/home',
    );

    $request = new Request();

    $response = $middleware->handle(
        $request,
        fn (Request $r) => new Response(body: 'login page', statusCode: 200),
    );

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/custom/home');
});

test('it supports specifying guard via parameter', function (): void {
    $user = new TestUser(id: 1);
    $configRepo = new GuestMiddlewareTestConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
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

    // GuestMiddleware using 'api' guard should allow through (user not authenticated on api guard)
    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
        guard: 'api',
    );

    $request = new Request();
    $expectedResponse = new Response(body: 'login page', statusCode: 200);

    $response = $middleware->handle(
        $request,
        fn (Request $r) => $expectedResponse,
    );

    // API guard user is not authenticated, so request passes through
    expect($response)->toBe($expectedResponse)
        ->and($response->statusCode())->toBe(200);
});
