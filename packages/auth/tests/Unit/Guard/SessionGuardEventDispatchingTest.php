<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Guard;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\CookieJarInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Event\FailedLoginEvent;
use Marko\Auth\Event\LoginEvent;
use Marko\Auth\Event\LogoutEvent;
use Marko\Auth\Guard\SessionGuard;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

/**
 * Create a stub session for testing.
 *
 * @return SessionInterface&object
 */
function createEventTestSessionStub(
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
 * Create a stub cookie jar for testing.
 */
function createEventTestCookieJarStub(
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
function createEventTestUserProviderStub(
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
function createEventTestUserStub(
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
 * Create a stub event dispatcher for testing.
 *
 * @param array<Event> $dispatchedEvents Reference to store dispatched events
 */
function createEventTestDispatcherStub(
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

test('it dispatches LoginEvent on successful login', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $provider = createEventTestUserProviderStub();
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);
    $user = createEventTestUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->user)->toBe($user);
});

test('it dispatches LoginEvent on successful attempt', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $user = createEventTestUserStub(id: 42);
    $provider = createEventTestUserProviderStub(userByCredentials: $user, credentialsValid: true);
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->user)->toBe($user);
});

test('it dispatches LogoutEvent on logout', function (): void {
    $storage = ['auth_user_id' => 42];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $user = createEventTestUserStub(id: 42);
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $provider = createEventTestUserProviderStub(userById: $user);
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Ensure user is logged in first
    expect($guard->check())->toBeTrue();

    // Logout
    $guard->logout();

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LogoutEvent::class)
        ->and($dispatchedEvents[0]->user)->toBe($user);
});

test('it dispatches FailedLoginEvent on failed attempt', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $user = createEventTestUserStub(id: 42);
    // User found but credentials invalid
    $provider = createEventTestUserProviderStub(userByCredentials: $user, credentialsValid: false);
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(FailedLoginEvent::class)
        ->and($dispatchedEvents[0]->credentials)->toBe(['email' => 'test@example.com']);
});

test('it includes guard name in events', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $provider = createEventTestUserProviderStub();
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);
    $user = createEventTestUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'admin',
        eventDispatcher: $dispatcher,
    );

    $guard->login($user);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->guard)->toBe('admin');
});

test('it includes remember flag in LoginEvent', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $dispatchedEvents = [];
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $provider = createEventTestUserProviderStub();
    $dispatcher = createEventTestDispatcherStub($dispatchedEvents);
    $user = createEventTestUserStub(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    // Test with remember = false
    $guard->login($user, remember: false);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->remember)->toBeFalse();

    // Reset
    $dispatchedEvents = [];
    $guard->logout();

    // Clear logout event
    $dispatchedEvents = [];

    // Test with remember = true
    $guard->login($user, remember: true);

    expect($dispatchedEvents)->toHaveCount(1)
        ->and($dispatchedEvents[0])->toBeInstanceOf(LoginEvent::class)
        ->and($dispatchedEvents[0]->remember)->toBeTrue();
});

test('event dispatching is optional (no error if dispatcher missing)', function (): void {
    $storage = [];
    $regenerateCalled = false;
    $session = createEventTestSessionStub($storage, $regenerateCalled);
    $user = createEventTestUserStub(id: 42);
    $provider = createEventTestUserProviderStub(userByCredentials: $user, credentialsValid: true, userById: $user);

    // Create guard without event dispatcher
    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        // No eventDispatcher provided
    );

    // These should not throw any errors
    $guard->login($user);
    expect($guard->check())->toBeTrue();

    $guard->logout();
    expect($guard->check())->toBeFalse();

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);
    expect($result)->toBeTrue();

    // Also test failed attempt
    $provider2 = createEventTestUserProviderStub(userByCredentials: $user, credentialsValid: false);
    $guard2 = new SessionGuard(
        session: createEventTestSessionStub($storage, $regenerateCalled),
        provider: $provider2,
        name: 'web',
    );

    $result2 = $guard2->attempt(['email' => 'test@example.com', 'password' => 'wrong']);
    expect($result2)->toBeFalse();
});
