<?php

declare(strict_types=1);

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\AuthManager;
use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Middleware\GuestMiddleware;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

// Test helpers
class GuestMiddlewareTestConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private array $values = [],
    ) {}

    public function get(
        string $key,
        mixed $default = null,
        ?string $scope = null,
    ): mixed {
        return $this->values[$key] ?? $default;
    }

    public function getString(
        string $key,
        ?string $default = null,
        ?string $scope = null,
    ): string {
        return (string) ($this->values[$key] ?? $default ?? '');
    }

    public function getInt(
        string $key,
        ?int $default = null,
        ?string $scope = null,
    ): int {
        return (int) ($this->values[$key] ?? $default ?? 0);
    }

    public function getBool(
        string $key,
        ?bool $default = null,
        ?string $scope = null,
    ): bool {
        return (bool) ($this->values[$key] ?? $default ?? false);
    }

    public function getFloat(
        string $key,
        ?float $default = null,
        ?string $scope = null,
    ): float {
        return (float) ($this->values[$key] ?? $default ?? 0.0);
    }

    public function getArray(
        string $key,
        ?array $default = null,
        ?string $scope = null,
    ): array {
        return (array) ($this->values[$key] ?? $default ?? []);
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

class GuestMiddlewareTestSession implements SessionInterface
{
    private array $storage = [];

    public bool $started {
        get => true;
    }

    public function start(): void {}

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->storage[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->storage[$key] = $value;
    }

    public function has(
        string $key,
    ): bool {
        return isset($this->storage[$key]);
    }

    public function remove(
        string $key,
    ): void {
        unset($this->storage[$key]);
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    public function all(): array
    {
        return $this->storage;
    }

    public function regenerate(bool $deleteOldSession = true): void {}

    public function destroy(): void
    {
        $this->storage = [];
    }

    public function getId(): string
    {
        return 'test-session-id';
    }

    public function setId(string $id): void {}

    public function flash(): FlashBag
    {
        return new FlashBag();
    }

    public function save(): void {}
}

class GuestMiddlewareTestUserProvider implements UserProviderInterface
{
    public function __construct(
        private ?AuthenticatableInterface $userById = null,
        private ?AuthenticatableInterface $userByCredentials = null,
        private bool $credentialsValid = false,
    ) {}

    public function retrieveById(
        int|string $identifier,
    ): ?AuthenticatableInterface {
        return $this->userById;
    }

    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface {
        return $this->userByCredentials;
    }

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool {
        return $this->credentialsValid;
    }

    public function retrieveByRememberToken(
        int|string $identifier,
        string $token,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
    ): void {}
}

class GuestMiddlewareTestUser implements AuthenticatableInterface
{
    private ?string $rememberToken = null;

    public function __construct(
        private int|string $id = 1,
        private string $password = 'hashed',
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
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(
        ?string $token,
    ): void {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}

// Helper function to create AuthManager with authenticated user
function createGuestAuthManagerWithUser(
    ?GuestMiddlewareTestUser $user = null,
): AuthManager {
    $configRepo = new GuestMiddlewareTestConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new GuestMiddlewareTestSession();
    $provider = new GuestMiddlewareTestUserProvider(
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
    $authManager = createGuestAuthManagerWithUser(null); // No user

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
    $user = new GuestMiddlewareTestUser(id: 1);
    $authManager = createGuestAuthManagerWithUser($user);

    $middleware = new GuestMiddleware(
        auth: $authManager,
        redirectTo: '/dashboard',
    );

    $request = new Request();
    $nextCalled = false;

    $response = $middleware->handle(
        $request,
        function (Request $r) use (&$nextCalled): Response {
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
    $user = new GuestMiddlewareTestUser(id: 1);
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
    $user = new GuestMiddlewareTestUser(id: 1);
    $configRepo = new GuestMiddlewareTestConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = new GuestMiddlewareTestSession();
    $provider = new GuestMiddlewareTestUserProvider(
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
