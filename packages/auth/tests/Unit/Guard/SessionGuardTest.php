<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Guard;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\CookieJarInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Exceptions\AuthException;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Token\RememberTokenManager;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

/**
 * Create a stub session for testing.
 *
 * @return SessionInterface&object
 */
function createSessionStub(
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
 * Create a stub cookie jar for testing.
 */
function createCookieJarStub(
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
 * Create a stub user provider for testing.
 */
function createUserProviderStub(
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
 * Create a stub authenticatable user for testing.
 */
function createUserStub(
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

test('it implements GuardInterface', function (): void {
    expect(class_exists(SessionGuard::class))->toBeTrue()
        ->and(is_subclass_of(SessionGuard::class, GuardInterface::class))->toBeTrue();
});

test('it stores user ID in session on login', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub();
    $user = createUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($storage['auth_user_id'])->toBe(42);
});

test('it retrieves user from session', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $retrievedUser = $guard->user();

    expect($retrievedUser)->toBe($user)
        ->and($retrievedUser->getAuthIdentifier())->toBe(42);
});

test('it returns true from check when authenticated', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeTrue();
});

test('it returns false from check when not authenticated', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeFalse();
});

test('it returns true from guest when not authenticated', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeTrue();
});

test('it returns false from guest when authenticated', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeFalse();
});

test('it returns user from user method when authenticated', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBe($user);
});

test('it returns null from user when not authenticated', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBeNull();
});

test('it attempts login with valid credentials', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userByCredentials: $user, credentialsValid: true);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($storage['auth_user_id'])->toBe(42);
});

test('it fails attempt with invalid credentials', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userByCredentials: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($storage)->not->toHaveKey('auth_user_id');
});

test('it logs out user and clears session', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    // First ensure user is logged in
    expect($guard->check())->toBeTrue();

    // Logout
    $guard->logout();

    // Verify session is cleared and user cache is invalidated
    expect($storage)->not->toHaveKey('auth_user_id')
        ->and($guard->check())->toBeFalse();
});

test('it regenerates session ID on login', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub();
    $user = createUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($regenerateCalled)->toBeTrue();
});

test('it throws AuthException when session not available', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createSessionStub($storage, $regenerateCalled, isStarted: false);
    $provider = createUserProviderStub();
    $user = createUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);
})->throws(
    AuthException::class,
    'Session not started',
);

// Remember Me Integration Tests

test('it creates remember token on login with remember flag', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $cookies = [];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    $guard->login($user, remember: true);

    expect($cookies)->toHaveKey('remember_web')
        ->and($cookies['remember_web'])->toBeString()
        ->and(strlen($cookies['remember_web']))->toBeGreaterThan(0);
});

test('it stores remember token in user provider', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $cookies = [];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    $guard->login($user, remember: true);

    // Provider should have received a hashed token
    expect($updatedRememberToken)->toBeString()
        ->and($updatedRememberToken)->not->toBeEmpty()
        // The stored token is a hash (64 characters for sha256)
        ->and(strlen($updatedRememberToken))->toBe(64);
});

test('it authenticates via remember token cookie', function (): void {
    $storage = []; // Empty session - no logged in user
    $regenerateCalled = false;
    $user = createUserStub(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookies = [
        'remember_web' => '42|' . $plainToken,
    ];

    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(
        userByRememberToken: $user,
        updatedRememberToken: $updatedRememberToken,
    );
    $cookieJar = createCookieJarStub($cookies);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // When no user is in session, it should check remember cookie
    $retrievedUser = $guard->user();

    expect($retrievedUser)->toBe($user)
        ->and($retrievedUser->getAuthIdentifier())->toBe(42);
});

test('it clears remember token on logout', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $user = createUserStub(id: 42);

    // Simulate existing remember token
    $tokenManager = new RememberTokenManager();
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookies = [
        'remember_web' => '42|' . $plainToken,
    ];

    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(
        userById: $user,
        updatedRememberToken: $updatedRememberToken,
    );
    $cookieJar = createCookieJarStub($cookies);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // First ensure user is logged in
    expect($guard->check())->toBeTrue();

    // Logout
    $guard->logout();

    // Verify remember cookie is cleared
    expect($cookies)->not->toHaveKey('remember_web')
        // And the user's token is set to null
        ->and($updatedRememberToken)->toBeNull();
});

test('it regenerates remember token on each use', function (): void {
    $storage = []; // Empty session - no logged in user
    $regenerateCalled = false;
    $user = createUserStub(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $originalPlainToken = $tokenManager->generate();
    $originalHashedToken = $tokenManager->hash($originalPlainToken);
    $user->setRememberToken($originalHashedToken);

    $cookies = [
        'remember_web' => '42|' . $originalPlainToken,
    ];
    $originalCookieValue = $cookies['remember_web'];

    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(
        userByRememberToken: $user,
        updatedRememberToken: $updatedRememberToken,
    );
    $cookieJar = createCookieJarStub($cookies);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Authenticate via remember cookie
    $guard->user();

    // Token should have been regenerated
    expect($updatedRememberToken)->toBeString()
        ->and($updatedRememberToken)->not->toBe($originalHashedToken)
        ->and($cookies['remember_web'])->not->toBe($originalCookieValue);
});

test('it does not create token when remember is false', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $cookies = [];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Login without remember flag (defaults to false)
    $guard->login($user);

    expect($cookies)->not->toHaveKey('remember_web')
        ->and($updatedRememberToken)->toBeNull();
});

test('it does not create token when remember is explicitly false', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $cookies = [];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $user = createUserStub(id: 42);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Login with explicitly false remember flag
    $guard->login($user);

    expect($cookies)->not->toHaveKey('remember_web')
        ->and($updatedRememberToken)->toBeNull();
});

test('it handles missing remember token gracefully', function (): void {
    $storage = []; // Empty session
    $regenerateCalled = false;
    $cookies = []; // No remember cookie
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Should return null without errors
    expect($guard->user())->toBeNull()
        ->and($guard->check())->toBeFalse()
        ->and($guard->guest())->toBeTrue();
});

test('it handles invalid remember token cookie format gracefully', function (): void {
    $storage = []; // Empty session
    $regenerateCalled = false;
    $cookies = [
        'remember_web' => 'invalid-format-no-pipe',
    ];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(updatedRememberToken: $updatedRememberToken);
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Should return null without errors
    expect($guard->user())->toBeNull();
});

test('it handles invalid remember token value gracefully', function (): void {
    $storage = []; // Empty session
    $regenerateCalled = false;
    $user = createUserStub(id: 42);

    // Set a different token on the user (simulating mismatch)
    $tokenManager = new RememberTokenManager();
    $validToken = $tokenManager->generate();
    $user->setRememberToken($tokenManager->hash($validToken));

    $cookies = [
        'remember_web' => '42|wrong_token_here',
    ];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    $provider = createUserProviderStub(
        userByRememberToken: $user,
        updatedRememberToken: $updatedRememberToken,
    );
    $cookieJar = createCookieJarStub($cookies);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Should return null because token doesn't validate
    expect($guard->user())->toBeNull();
});

test('it handles user not found by remember token gracefully', function (): void {
    $storage = []; // Empty session
    $regenerateCalled = false;
    $cookies = [
        'remember_web' => '999|some_token_value',
    ];
    $updatedRememberToken = null;
    $session = createSessionStub($storage, $regenerateCalled);
    // No user found by remember token
    $provider = createUserProviderStub(
        updatedRememberToken: $updatedRememberToken,
    );
    $cookieJar = createCookieJarStub($cookies);
    $tokenManager = new RememberTokenManager();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Should return null without errors
    expect($guard->user())->toBeNull();
});
