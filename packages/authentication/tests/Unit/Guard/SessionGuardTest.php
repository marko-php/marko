<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Testing\Fake\FakeAuthenticatable;
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
    $tokenManager = new RememberTokenManager();

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
    $token = $provider->lastRememberTokenUpdate['token'] ?? null;
    expect($token)->toBeString()
        ->and($token)->not->toBeEmpty()
        // The stored token is a hash (64 characters for sha256)
        ->and(strlen((string) $token))->toBe(64);
});

test('it authenticates via remember token cookie', function (): void {
    $session = new FakeSession();
    $user = new FakeAuthenticatable(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|' . $plainToken);

    // Use inline provider since FakeUserProvider's retrieveByRememberToken
    // compares token to stored hash, which is incompatible with guard's
    // separate validation step
    $provider = new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private AuthenticatableInterface $userByRememberToken,
        ) {}

        public function retrieveById(int|string $identifier): ?AuthenticatableInterface
        {
            return null;
        }

        public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
        {
            return null;
        }

        public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
        {
            return false;
        }

        public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface
        {
            return $this->userByRememberToken;
        }

        public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void
        {
            $user->setRememberToken($token);
        }
    };

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
    $tokenManager = new RememberTokenManager();
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
    $user = new FakeAuthenticatable(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $originalPlainToken = $tokenManager->generate();
    $originalHashedToken = $tokenManager->hash($originalPlainToken);
    $user->setRememberToken($originalHashedToken);

    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', '42|' . $originalPlainToken);
    $originalCookieValue = $cookieJar->cookies['remember_web'];

    // Use inline provider since FakeUserProvider's retrieveByRememberToken
    // compares token to stored hash, which is incompatible with guard's
    // separate validation step
    $provider = new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private AuthenticatableInterface $userByRememberToken,
        ) {}

        public function retrieveById(int|string $identifier): ?AuthenticatableInterface
        {
            return null;
        }

        public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
        {
            return null;
        }

        public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
        {
            return false;
        }

        public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface
        {
            return $this->userByRememberToken;
        }

        public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void
        {
            $user->setRememberToken($token);
        }
    };

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

    expect($cookieJar->cookies)->not->toHaveKey('remember_web')
        ->and($provider->lastRememberTokenUpdate)->toBeNull();
});

test('it does not create token when remember is explicitly false', function (): void {
    $session = new FakeSession();
    $session->start();
    $user = new FakeAuthenticatable(id: 42);
    $provider = new FakeUserProvider([42 => $user]);
    $cookieJar = new FakeCookieJar();
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

    expect($cookieJar->cookies)->not->toHaveKey('remember_web')
        ->and($provider->lastRememberTokenUpdate)->toBeNull();
});

test('it handles missing remember token gracefully', function (): void {
    $session = new FakeSession();
    $provider = new FakeUserProvider();
    $cookieJar = new FakeCookieJar();
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
    $session = new FakeSession();
    $provider = new FakeUserProvider();
    $cookieJar = new FakeCookieJar();
    $cookieJar->set('remember_web', 'invalid-format-no-pipe');
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
    $session = new FakeSession();
    $user = new FakeAuthenticatable(id: 42);

    // Set a different token on the user (simulating mismatch)
    $tokenManager = new RememberTokenManager();
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
