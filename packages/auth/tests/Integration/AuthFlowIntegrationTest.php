<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Integration;

use Closure;
use Marko\Auth\AuthManager;
use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Contracts\PasswordHasherInterface;
use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Guard\TokenGuard;
use Marko\Auth\Token\RememberTokenManager;
use Marko\Config\ConfigRepositoryInterface;

/**
 * Create a stub config repository for integration testing.
 *
 * @param array<string, mixed> $values
 */
function createIntegrationConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new readonly class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
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
    };
}

test('complete login flow works', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
    );

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // Initial state - not authenticated
    expect($manager->check())->toBeFalse()
        ->and($manager->user())->toBeNull()
        ->and($manager->id())->toBeNull();

    // Attempt login
    $result = $manager->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    // Verify login succeeded
    expect($result)->toBeTrue()
        ->and($manager->check())->toBeTrue()
        ->and($manager->user())->toBe($user)
        ->and($manager->id())->toBe(42)
        ->and($session->regenerateCalled)->toBeTrue();
});

test('complete logout flow works', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
    );

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // Login first
    $manager->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    expect($manager->check())->toBeTrue();

    // Logout
    $manager->logout();

    // Verify logout succeeded
    expect($manager->check())->toBeFalse()
        ->and($manager->user())->toBeNull()
        ->and($manager->id())->toBeNull();
});

test('remember me creates and uses token', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();
    $cookieJar = new TestCookieJar();
    $tokenManager = new RememberTokenManager();
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
        userByRememberToken: $user,
    );

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Login with remember = true
    $guard->login($user, remember: true);

    // Verify remember token was created
    expect($provider->lastUpdatedRememberToken)->not->toBeNull()
        ->and($cookieJar->cookies)->toHaveKey('remember_web')
        ->and($cookieJar->cookies['remember_web'])->toContain('42|');

    // Verify user has remember token set
    $storedHash = $user->getRememberToken();
    expect($storedHash)->not->toBeNull();

    // Extract token from cookie
    $cookieValue = $cookieJar->cookies['remember_web'];
    $parts = explode('|', $cookieValue);
    expect($parts)->toHaveCount(2);
    [, $plainToken] = $parts;

    // Verify the token validates against stored hash
    expect($tokenManager->validate($plainToken, $storedHash))->toBeTrue();
});

test('guard switching works correctly', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
            'admin' => ['driver' => 'session', 'provider' => 'admins'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
    );

    $manager = new AuthManager(
        config: $authConfig,
        session: $session,
        provider: $provider,
    );

    // Get different guards
    $webGuard = $manager->guard('web');
    $apiGuard = $manager->guard('api');
    $adminGuard = $manager->guard('admin');

    // Verify correct guard types
    expect($webGuard)->toBeInstanceOf(SessionGuard::class)
        ->and($apiGuard)->toBeInstanceOf(TokenGuard::class)
        ->and($adminGuard)->toBeInstanceOf(SessionGuard::class)
        ->and($webGuard->getName())->toBe('web')
        ->and($apiGuard->getName())->toBe('api')
        ->and($adminGuard->getName())->toBe('admin')
        ->and($webGuard)->not->toBe($apiGuard)
        ->and($webGuard)->not->toBe($adminGuard)
        ->and($apiGuard)->not->toBe($adminGuard);

    // Verify guard names

    // Verify they are different instances

    // Login on web guard only
    $webGuard->login($user);

    // Verify authentication is guard-scoped
    expect($webGuard->check())->toBeTrue()
        ->and($apiGuard->check())->toBeFalse();

    // Admin guard shares session, so it may be authenticated depending on implementation
    // The current implementation stores session with 'auth_user_id' key
    // which is shared across all session guards
});

test('module bindings resolve correctly', function (): void {
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $config = require $modulePath;

    // Verify module structure
    expect($config)->toBeArray()
        ->and($config)->toHaveKey('bindings')
        ->and($config['bindings'])->toBeArray()
        ->and($config['bindings'])->toHaveKey(PasswordHasherInterface::class)
        ->and($config['bindings'])->toHaveKey(AuthManager::class)
        ->and($config['bindings'][PasswordHasherInterface::class])->toBeInstanceOf(Closure::class)
        ->and($config['bindings'][AuthManager::class])->toBeInstanceOf(Closure::class);

    // Verify required bindings exist

    // Verify bindings are closures
});

test('config loading works', function (): void {
    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'api',
        'auth.default.provider' => 'customers',
        'auth.guards' => [
            'web' => ['driver' => 'session'],
            'api' => ['driver' => 'token'],
        ],
        'auth.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\User'],
            'customers' => ['driver' => 'database', 'table' => 'customers'],
        ],
        'auth.password' => [
            'bcrypt' => ['cost' => 10],
        ],
        'auth.password.bcrypt.cost' => 10,
        'auth.remember' => [
            'lifetime' => 60 * 24 * 30, // 30 days
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);

    // Verify defaults
    expect($authConfig->defaultGuard())->toBe('api')
        ->and($authConfig->defaultProvider())->toBe('customers');

    // Verify guards
    $guards = $authConfig->guards();
    expect($guards)->toHaveKey('web')
        ->and($guards)->toHaveKey('api')
        ->and($guards['web']['driver'])->toBe('session')
        ->and($guards['api']['driver'])->toBe('token');

    // Verify providers
    $providers = $authConfig->providers();
    expect($providers)->toHaveKey('users')
        ->and($providers)->toHaveKey('customers')
        ->and($authConfig->bcryptCost())->toBe(10);

    // Verify password config

    // Verify remember config
    $rememberConfig = $authConfig->rememberConfig();
    expect($rememberConfig)->toHaveKey('lifetime')
        ->and($rememberConfig['lifetime'])->toBe(43200);
});

test('events dispatched during auth flow', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();
    $dispatcher = new TestEventDispatcher();
    $provider = new TestUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
    );

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Test successful login event
    $guard->login($user);

    expect($dispatcher->events)->toHaveCount(1);
    $loginEvent = $dispatcher->events[0];
    assert($loginEvent instanceof LoginEvent);
    expect($loginEvent->user)->toBe($user)
        ->and($loginEvent->guard)->toBe('web')
        ->and($loginEvent->remember)->toBeFalse();

    // Reset events and test logout
    $dispatcher->clear();
    $guard->logout();

    expect($dispatcher->events)->toHaveCount(1);
    $logoutEvent = $dispatcher->events[0];
    assert($logoutEvent instanceof LogoutEvent);
    expect($logoutEvent->user)->toBe($user)
        ->and($logoutEvent->guard)->toBe('web');

    // Reset events and test failed login
    $dispatcher->clear();
    $invalidProvider = new TestUserProvider(
        userByCredentials: $user,
    );

    $failGuard = new SessionGuard(
        session: new TestSession(),
        provider: $invalidProvider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $failGuard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($dispatcher->events)->toHaveCount(1);
    $failedEvent = $dispatcher->events[0];
    assert($failedEvent instanceof FailedLoginEvent);
    expect($failedEvent->guard)->toBe('web')
        ->and($failedEvent->credentials)->toBe(['email' => 'test@example.com']);
});
