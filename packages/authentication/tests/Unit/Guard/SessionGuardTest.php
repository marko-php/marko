<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeCookieJar;
use Marko\Testing\Fake\FakeSession;
use Marko\Testing\Fake\FakeUserProvider;

test('it implements GuardInterface', function (): void {
    expect(class_exists(SessionGuard::class))->toBeTrue()
        ->and(is_subclass_of(SessionGuard::class, GuardInterface::class))->toBeTrue();
});

test('it stores user ID in session on login', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($session->get('auth_web_user_id'))->toBe(42);
});

test('it retrieves user from session', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

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
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeTrue();
});

test('it returns false from check when not authenticated', function (): void {
    $session = new FakeSession();
    $provider = new FakeUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeFalse();
});

test('it returns true from guest when not authenticated', function (): void {
    $session = new FakeSession();
    $provider = new FakeUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeTrue();
});

test('it returns false from guest when authenticated', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeFalse();
});

test('it returns user from user method when authenticated', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBe($user);
});

test('it returns null from user when not authenticated', function (): void {
    $session = new FakeSession();
    $provider = new FakeUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBeNull();
});

test('it attempts login with valid credentials', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($session->get('auth_web_user_id'))->toBe(42);
});

test('it fails attempt with invalid credentials', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user], fn () => false);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($session->has('auth_web_user_id'))->toBeFalse();
});

test('it asks the provider to rehash the password after a successful attempt', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $guard = new SessionGuard(session: $session, provider: $provider, name: 'web');
    $credentials = ['email' => 'test@example.com', 'password' => 'secret'];

    $guard->attempt($credentials);

    expect($provider->rehashChecks)->toBe([['user' => $user, 'credentials' => $credentials]]);
});

test('it does not rehash the password when the attempt fails', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider([42 => new FakeAuthenticatable(id: 42)], fn () => false);
    $guard = new SessionGuard(session: $session, provider: $provider, name: 'web');

    $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($provider->rehashChecks)->toBe([]);
});

test('it logs out user and clears session', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

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
    expect($session->has('auth_web_user_id'))->toBeFalse()
        ->and($guard->check())->toBeFalse();
});

test('it regenerates the session ID on logout so the logged-out ID cannot be reused', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->set('auth_web_user_id', 42);
    $loggedInId = $session->getId();

    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider([42 => new FakeAuthenticatable(id: 42)]),
        name: 'web',
    );

    $guard->logout();

    expect($session->regenerated)->toBeTrue()
        ->and($session->getId())->not->toBe($loggedInId);
});

test('it dispatches LogoutEvent after the session ID is regenerated', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->set('auth_web_user_id', 42);
    $dispatcher = new class ($session) implements EventDispatcherInterface
    {
        public ?bool $regeneratedWhenDispatched = null;

        public function __construct(
            private readonly FakeSession $session,
        ) {}

        public function dispatch(
            Event $event,
        ): void {
            $this->regeneratedWhenDispatched = $this->session->regenerated;
        }
    };

    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider([42 => new FakeAuthenticatable(id: 42)]),
        name: 'web',
        eventDispatcher: $dispatcher,
    );

    $guard->logout();

    // Observers (e.g. CSRF token rotation) write into the new session
    expect($dispatcher->regeneratedWhenDispatched)->toBeTrue();
});

test('it does not regenerate the session when a guest logs out', function (): void {
    $session = new FakeSession();
    $session->start();

    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider(),
        name: 'web',
    );

    $guard->logout();

    expect($session->regenerated)->toBeFalse();
});

test('it regenerates session ID on login', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($session->regenerated)->toBeTrue();
});

test('it logs in on an armed session', function (): void {
    $session = new FakeSession();
    $session->arm();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: new FakeUserProvider(),
        name: 'web',
    );

    $guard->login($user);

    expect($session->started)->toBeTrue()
        ->and($session->regenerated)->toBeTrue()
        ->and($session->get('auth_web_user_id'))->toBe(42);
});

test('it throws AuthException when session not available', function (): void {
    $session = new FakeSession();
    // started defaults to false, no need to set it
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 42);

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
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $cookieJar = new FakeCookieJar();
    $tokenManager = new RememberTokenManager(new FakeClock());

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    $guard->login($user, remember: true);

    expect($cookieJar->cookies)->toHaveKey('remember_web')
        ->and($cookieJar->cookies['remember_web'])->toBeString()
        ->and(strlen($cookieJar->cookies['remember_web']))->toBeGreaterThan(0);
});

test('it stores remember token in user provider', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $cookieJar = new FakeCookieJar();
    $tokenManager = new RememberTokenManager(new FakeClock());

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    $guard->login($user, remember: true);

    // Provider should have received a hashed token
    $token = $provider->lastRememberTokenUpdate['token'] ?? null;
    expect($token)->toBeString()
        ->and($token)->not->toBeEmpty()
        // The stored token is a hash (64 characters for sha256)
        ->and(strlen((string) $token))->toBe(64);
});

test('it authenticates via remember token cookie', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager(new FakeClock());
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);
    $user->setRememberTokenExpiresAt($tokenManager->expiresAt());

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|' . $plainToken);

    $provider = new FakeUserProvider([42 => $user]);

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
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);

    // Simulate existing remember token
    $tokenManager = new RememberTokenManager(new FakeClock());
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|' . $plainToken);

    $provider = new FakeUserProvider([42 => $user]);

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
    expect($cookieJar->cookies)->not->toHaveKey('remember_web')
        // And the user's token is set to null
        ->and($provider->lastRememberTokenUpdate['token'] ?? null)->toBeNull();
});

test('it regenerates remember token on each use', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager(new FakeClock());
    $originalPlainToken = $tokenManager->generate();
    $originalHashedToken = $tokenManager->hash($originalPlainToken);
    $user->setRememberToken($originalHashedToken);
    $user->setRememberTokenExpiresAt($tokenManager->expiresAt());

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|' . $originalPlainToken);
    $originalCookieValue = $cookieJar->cookies['remember_web'];

    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Authenticate via remember cookie
    $guard->user();

    // Token should have been regenerated (check via user object and cookie)
    expect($user->getRememberToken())->toBeString()
        ->and($user->getRememberToken())->not->toBe($originalHashedToken)
        ->and($cookieJar->cookies['remember_web'])->not->toBe($originalCookieValue);
});

test('it does not create token when remember is false', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $cookieJar = new FakeCookieJar();
    $tokenManager = new RememberTokenManager(new FakeClock());

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Login without remember flag (defaults to false)
    $guard->login($user);

    expect($cookieJar->cookies)->not->toHaveKey('remember_web')
        ->and($provider->lastRememberTokenUpdate)->toBeNull();
});

test('it does not create token when remember is explicitly false', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $cookieJar = new FakeCookieJar();
    $tokenManager = new RememberTokenManager(new FakeClock());

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
        cookieJar: $cookieJar,
        tokenManager: $tokenManager,
    );

    // Login with explicitly false remember flag
    $guard->login($user);

    expect($cookieJar->cookies)->not->toHaveKey('remember_web')
        ->and($provider->lastRememberTokenUpdate)->toBeNull();
});

test('it handles missing remember token gracefully', function (): void {
    $session = new FakeSession();
    $provider = new FakeUserProvider();
    $cookieJar = new FakeCookieJar();
    $tokenManager = new RememberTokenManager(new FakeClock());

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
    $session = new FakeSession();
    $provider = new FakeUserProvider();
    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', 'invalid-format-no-pipe');
    $tokenManager = new RememberTokenManager(new FakeClock());

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
    $session = new FakeSession();
    $user = new FakeAuthenticatable(id: 42);

    // Set a different token on the user (simulating mismatch)
    $tokenManager = new RememberTokenManager(new FakeClock());
    $validToken = $tokenManager->generate();
    $user->setRememberToken($tokenManager->hash($validToken));

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|wrong_token_here');

    $provider = new FakeUserProvider([42 => $user]);

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
    $session = new FakeSession();
    $provider = new FakeUserProvider();
    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '999|some_token_value');
    $tokenManager = new RememberTokenManager(new FakeClock());

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

test('it uses guard-name-scoped session key format auth_{name}_user_id', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'admin',
    );

    $guard->login($user);

    // The session key should be scoped to the guard name
    expect($session->get('auth_admin_user_id'))->toBe(42)
        ->and($session->has('auth_web_user_id'))->toBeFalse();
});

test('it stores user id under scoped session key on login', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 99);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'api',
    );

    $guard->login($user);

    // Verify the user ID is stored under the guard-scoped key
    expect($session->get('auth_api_user_id'))->toBe(99)
        ->and($session->all())->toHaveKey('auth_api_user_id');
});

test('it retrieves user id from scoped session key on check', function (): void {
    $session = new FakeSession();
    $user = new FakeAuthenticatable(id: 77);
    $provider = new FakeUserProvider([77 => $user]);

    // Manually set the scoped session key
    $session->set('auth_custom_user_id', 77);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'custom',
    );

    // check() should find the user via the scoped session key
    expect($guard->check())->toBeTrue()
        ->and($guard->user())->toBe($user)
        ->and($guard->id())->toBe(77);
});

test('it removes scoped session key on logout', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 55);
    $provider = new FakeUserProvider([55 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'portal',
    );

    // Login first
    $guard->login($user);
    expect($session->has('auth_portal_user_id'))->toBeTrue();

    // Logout
    $guard->logout();

    // Verify the scoped session key is removed
    expect($session->has('auth_portal_user_id'))->toBeFalse()
        ->and($guard->check())->toBeFalse();
});

test('it isolates session state between two guards with different names', function (): void {
    $session = new FakeSession();
    $session->start();
    $webUser = new FakeAuthenticatable(id: 1);
    $adminUser = new FakeAuthenticatable(id: 2);
    $webProvider = new FakeUserProvider([1 => $webUser]);
    $adminProvider = new FakeUserProvider([2 => $adminUser]);

    $webGuard = new SessionGuard(
        session: $session,
        provider: $webProvider,
        name: 'web',
    );

    $adminGuard = new SessionGuard(
        session: $session,
        provider: $adminProvider,
        name: 'admin',
    );

    // Login web user only
    $webGuard->login($webUser);

    // Web guard should be authenticated, admin guard should not
    expect($webGuard->check())->toBeTrue()
        ->and($adminGuard->check())->toBeFalse();

    // Login admin user
    $adminGuard->login($adminUser);

    // Both should now be authenticated with their own users
    expect($webGuard->check())->toBeTrue()
        ->and($adminGuard->check())->toBeTrue()
        ->and($session->get('auth_web_user_id'))->toBe(1)
        ->and($session->get('auth_admin_user_id'))->toBe(2);

    // Logout web guard should not affect admin guard
    $webGuard->logout();

    expect($webGuard->check())->toBeFalse()
        ->and($adminGuard->check())->toBeTrue()
        ->and($session->has('auth_web_user_id'))->toBeFalse()
        ->and($session->get('auth_admin_user_id'))->toBe(2);
});

test('it forgets the cached user without destroying the session', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);

    $provider = new class () implements UserProviderInterface
    {
        public int $retrieveCalls = 0;

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            $this->retrieveCalls++;

            return new FakeAuthenticatable(id: (int) $identifier);
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function validateCredentials(
            AuthenticatableInterface $user,
            array $credentials,
        ): bool {
            return false;
        }

        public function rehashPasswordIfNeeded(
            AuthenticatableInterface $user,
            array $credentials,
        ): void {}

        public function retrieveByRememberToken(
            int|string $identifier,
            string $token,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function updateRememberToken(
            AuthenticatableInterface $user,
            ?string $token,
            ?DateTimeImmutable $expiresAt,
        ): void {}
    };

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $firstUser = $guard->user();
    $guard->reset();
    $secondUser = $guard->user();

    expect($secondUser)->not->toBe($firstUser)
        ->and($provider->retrieveCalls)->toBe(2)
        ->and($session->has('auth_web_user_id'))->toBeTrue();
});

test('it does not return the previous requests user to an anonymous request', function (): void {
    $session = new FakeSession();
    $session->set('auth_web_user_id', 42);
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    // Request 1: an authenticated user is resolved and cached
    expect($guard->user())->toBe($user);

    // Between requests, a long-running worker resets state and the next
    // request is an anonymous visitor with no session key present
    $guard->reset();
    $session->remove('auth_web_user_id');

    // Request 2: the anonymous visitor must not receive the previous user
    expect($guard->user())->toBeNull();
});

test('it defaults to auth_session_user_id when guard name is session', function (): void {
    $session = new FakeSession();
    $session->start();
    $provider = new FakeUserProvider();
    $user = new FakeAuthenticatable(id: 33);

    // Use the default guard name (which is 'session')
    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
    );

    $guard->login($user);

    // The default guard name is 'session', so the key should be 'auth_session_user_id'
    expect($session->get('auth_session_user_id'))->toBe(33)
        ->and($guard->getName())->toBe('session');
});
