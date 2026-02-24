<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Integration;

use Closure;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Guard\TokenGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

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
    };
}

test('complete login flow works', function (): void {
    $user = new TestUser(id: 42);
    $session = new TestSession();

    $configRepo = createIntegrationConfigRepository([
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
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
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
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
        'authentication.default.guard' => 'web',
        'authentication.guards' => [
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

    // Admin guard shares session storage, but each guard uses its own scoped key
    // (e.g., 'auth_web_user_id' vs 'auth_admin_user_id'), so they are isolated
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
        'authentication.default.guard' => 'api',
        'authentication.default.provider' => 'customers',
        'authentication.guards' => [
            'web' => ['driver' => 'session'],
            'api' => ['driver' => 'token'],
        ],
        'authentication.providers' => [
            'users' => ['driver' => 'eloquent', 'model' => 'App\\User'],
            'customers' => ['driver' => 'database', 'table' => 'customers'],
        ],
        'authentication.password' => [
            'bcrypt' => ['cost' => 10],
        ],
        'authentication.password.bcrypt.cost' => 10,
        'authentication.remember' => [
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
