<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Integration;

use Closure;
use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\AuthManager;
use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Contracts\CookieJarInterface;
use Marko\Auth\Contracts\PasswordHasherInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Guard\TokenGuard;
use Marko\Auth\Token\RememberTokenManager;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

/**
 * Create a stub config repository for integration testing.
 *
 * @param array<string, mixed> $values
 */
function createIntegrationConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new class ($values) implements ConfigRepositoryInterface
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

/**
 * Create a stub session for integration testing.
 *
 * @param array<string, mixed> $storage
 */
function createIntegrationSession(
    array &$storage = [],
    bool &$regenerateCalled = false,
    bool $isStarted = true,
): SessionInterface {
    return new class ($storage, $regenerateCalled, $isStarted) implements SessionInterface
    {
        public function __construct(
            private array &$storage,
            private bool &$regenerateCalled,
            private bool $isStartedValue,
        ) {}

        public bool $started {
            get => $this->isStartedValue;
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

        public function regenerate(
            bool $deleteOldSession = true,
        ): void {
            $this->regenerateCalled = true;
        }

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
    };
}

/**
 * Create a stub cookie jar for integration testing.
 *
 * @param array<string, string> $cookies
 */
function createIntegrationCookieJar(
    array &$cookies = [],
): CookieJarInterface {
    return new class ($cookies) implements CookieJarInterface
    {
        public function __construct(
            private array &$cookies,
        ) {}

        public function get(
            string $name,
        ): ?string {
            return $this->cookies[$name] ?? null;
        }

        public function set(
            string $name,
            string $value,
            int $minutes = 0,
        ): void {
            $this->cookies[$name] = $value;
        }

        public function delete(
            string $name,
        ): void {
            unset($this->cookies[$name]);
        }
    };
}

/**
 * Create an authenticatable user for integration testing.
 */
function createIntegrationUser(
    int|string $id = 1,
    string $password = 'hashed',
): AuthenticatableInterface {
    return new class ($id, $password) implements AuthenticatableInterface
    {
        private ?string $rememberToken = null;

        public function __construct(
            private int|string $id,
            private string $password,
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
    };
}

/**
 * Create a user provider for integration testing.
 */
function createIntegrationUserProvider(
    ?AuthenticatableInterface $userById = null,
    ?AuthenticatableInterface $userByCredentials = null,
    bool $credentialsValid = false,
    ?AuthenticatableInterface $userByRememberToken = null,
    ?string &$updatedRememberToken = null,
): UserProviderInterface {
    return new class ($userById, $userByCredentials, $credentialsValid, $userByRememberToken, $updatedRememberToken) implements UserProviderInterface
    {
        public function __construct(
            private ?AuthenticatableInterface $userById,
            private ?AuthenticatableInterface $userByCredentials,
            private bool $credentialsValid,
            private ?AuthenticatableInterface $userByRememberToken,
            private ?string &$updatedRememberToken,
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
            return $this->userByRememberToken;
        }

        public function updateRememberToken(
            AuthenticatableInterface $user,
            ?string $token,
        ): void {
            $this->updatedRememberToken = $token;
            $user->setRememberToken($token);
        }
    };
}

/**
 * Create an event dispatcher for integration testing.
 *
 * @param array<Event> $dispatchedEvents
 */
function createIntegrationEventDispatcher(
    array &$dispatchedEvents = [],
): EventDispatcherInterface {
    return new class ($dispatchedEvents) implements EventDispatcherInterface
    {
        public function __construct(
            private array &$dispatchedEvents,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->dispatchedEvents[] = $event;
        }
    };
}

test('complete login flow works', function (): void {
    $user = createIntegrationUser(id: 42);
    $storage = [];
    $regenerateCalled = false;

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = createIntegrationSession($storage, $regenerateCalled);
    $provider = createIntegrationUserProvider(
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
        ->and($regenerateCalled)->toBeTrue();
});

test('complete logout flow works', function (): void {
    $user = createIntegrationUser(id: 42);
    $storage = [];
    $regenerateCalled = false;

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = createIntegrationSession($storage, $regenerateCalled);
    $provider = createIntegrationUserProvider(
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
    $user = createIntegrationUser(id: 42);
    $storage = [];
    $cookies = [];
    $regenerateCalled = false;
    $updatedRememberToken = null;

    $session = createIntegrationSession($storage, $regenerateCalled);
    $cookieJar = createIntegrationCookieJar($cookies);
    $tokenManager = new RememberTokenManager();
    $provider = createIntegrationUserProvider(
        userById: $user,
        userByCredentials: $user,
        credentialsValid: true,
        userByRememberToken: $user,
        updatedRememberToken: $updatedRememberToken,
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
    expect($updatedRememberToken)->not->toBeNull()
        ->and($cookies)->toHaveKey('remember_web')
        ->and($cookies['remember_web'])->toContain('42|');

    // Verify user has remember token set
    $storedHash = $user->getRememberToken();
    expect($storedHash)->not->toBeNull();

    // Extract token from cookie
    $cookieValue = $cookies['remember_web'];
    $parts = explode('|', $cookieValue);
    expect($parts)->toHaveCount(2);
    [$userId, $plainToken] = $parts;

    // Verify the token validates against stored hash
    expect($tokenManager->validate($plainToken, $storedHash))->toBeTrue();
});

test('guard switching works correctly', function (): void {
    $user = createIntegrationUser(id: 42);
    $storage = [];
    $regenerateCalled = false;

    $configRepo = createIntegrationConfigRepository([
        'auth.default.guard' => 'web',
        'auth.guards' => [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'token', 'provider' => 'users'],
            'admin' => ['driver' => 'session', 'provider' => 'admins'],
        ],
    ]);

    $authConfig = new AuthConfig($configRepo);
    $session = createIntegrationSession($storage, $regenerateCalled);
    $provider = createIntegrationUserProvider(
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
        ->and($adminGuard)->toBeInstanceOf(SessionGuard::class);

    // Verify guard names
    expect($webGuard->getName())->toBe('web')
        ->and($apiGuard->getName())->toBe('api')
        ->and($adminGuard->getName())->toBe('admin');

    // Verify they are different instances
    expect($webGuard)->not->toBe($apiGuard)
        ->and($webGuard)->not->toBe($adminGuard)
        ->and($apiGuard)->not->toBe($adminGuard);

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
        ->and($config['bindings'])->toBeArray();

    // Verify required bindings exist
    expect($config['bindings'])->toHaveKey(PasswordHasherInterface::class)
        ->and($config['bindings'])->toHaveKey(AuthManager::class);

    // Verify bindings are closures
    expect($config['bindings'][PasswordHasherInterface::class])->toBeInstanceOf(Closure::class)
        ->and($config['bindings'][AuthManager::class])->toBeInstanceOf(Closure::class);
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
        ->and($providers)->toHaveKey('customers');

    // Verify password config
    expect($authConfig->bcryptCost())->toBe(10);

    // Verify remember config
    $rememberConfig = $authConfig->rememberConfig();
    expect($rememberConfig)->toHaveKey('lifetime')
        ->and($rememberConfig['lifetime'])->toBe(43200);
});

test('events dispatched during auth flow', function (): void {
    $user = createIntegrationUser(id: 42);
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];

    $session = createIntegrationSession($storage, $regenerateCalled);
    $dispatcher = createIntegrationEventDispatcher($dispatchedEvents);
    $provider = createIntegrationUserProvider(
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

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->user)->toBe($user)
        ->and($dispatchedEvents[0]->guard)->toBe('web')
        ->and($dispatchedEvents[0]->remember)->toBeFalse();

    // Reset events and test logout
    $dispatchedEvents = [];
    $guard->logout();

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LogoutEvent::class)
        ->and($dispatchedEvents[0]->user)->toBe($user)
        ->and($dispatchedEvents[0]->guard)->toBe('web');

    // Reset events and test failed login
    $dispatchedEvents = [];
    $invalidProvider = createIntegrationUserProvider(
        userByCredentials: $user,
    );

    $failGuard = new SessionGuard(
        session: createIntegrationSession($storage, $regenerateCalled),
        provider: $invalidProvider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $failGuard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(FailedLoginEvent::class)
        ->and($dispatchedEvents[0]->guard)->toBe('web')
        ->and($dispatchedEvents[0]->credentials)->toBe(['email' => 'test@example.com']);
});
