<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Guard;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Exceptions\AuthException;
use Marko\Auth\Guard\SessionGuard;
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
            private bool $isStarted,
        ) {}

        public function start(): void {}

        public function isStarted(): bool
        {
            return $this->isStarted;
        }

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
 * Create a stub user provider for testing.
 */
function createUserProviderStub(
    ?AuthenticatableInterface $userById = null,
    ?AuthenticatableInterface $userByCredentials = null,
    bool $credentialsValid = false,
): UserProviderInterface {
    return new class ($userById, $userByCredentials, $credentialsValid) implements UserProviderInterface
    {
        public function __construct(
            private ?AuthenticatableInterface $userById,
            private ?AuthenticatableInterface $userByCredentials,
            private bool $credentialsValid,
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
    $provider = createUserProviderStub(userByCredentials: $user, credentialsValid: false);

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
