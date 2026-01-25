<?php

declare(strict_types=1);

namespace Marko\Auth\Tests\Unit\Guard;

use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Exceptions\AuthException;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Tests\Integration\TestCookieJar;
use Marko\Auth\Tests\Integration\TestSession;
use Marko\Auth\Tests\Integration\TestUser;
use Marko\Auth\Tests\Integration\TestUserProvider;
use Marko\Auth\Token\RememberTokenManager;

test('it implements GuardInterface', function (): void {
    expect(class_exists(SessionGuard::class))->toBeTrue()
        ->and(is_subclass_of(SessionGuard::class, GuardInterface::class))->toBeTrue();
});

test('it stores user ID in session on login', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $user = new TestUser(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($session->get('auth_user_id'))->toBe(42);
});

test('it retrieves user from session', function (): void {
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userById: $user);

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
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeTrue();
});

test('it returns false from check when not authenticated', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->check())->toBeFalse();
});

test('it returns true from guest when not authenticated', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeTrue();
});

test('it returns false from guest when authenticated', function (): void {
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->guest())->toBeFalse();
});

test('it returns user from user method when authenticated', function (): void {
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userById: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBe($user);
});

test('it returns null from user when not authenticated', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    expect($guard->user())->toBeNull();
});

test('it attempts login with valid credentials', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userByCredentials: $user, credentialsValid: true);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'secret']);

    expect($result)->toBeTrue()
        ->and($session->get('auth_user_id'))->toBe(42);
});

test('it fails attempt with invalid credentials', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userByCredentials: $user);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $result = $guard->attempt(['email' => 'test@example.com', 'password' => 'wrong']);

    expect($result)->toBeFalse()
        ->and($session->has('auth_user_id'))->toBeFalse();
});

test('it logs out user and clears session', function (): void {
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider(userById: $user);

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
    expect($session->has('auth_user_id'))->toBeFalse()
        ->and($guard->check())->toBeFalse();
});

test('it regenerates session ID on login', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $user = new TestUser(id: 42);

    $guard = new SessionGuard(
        session: $session,
        provider: $provider,
        name: 'web',
    );

    $guard->login($user);

    expect($session->regenerateCalled)->toBeTrue();
});

test('it throws AuthException when session not available', function (): void {
    $session = new TestSession();
    $session->started = false;
    $provider = new TestUserProvider();
    $user = new TestUser(id: 42);

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
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
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
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
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
    expect($provider->lastUpdatedRememberToken)->toBeString()
        ->and($provider->lastUpdatedRememberToken)->not->toBeEmpty()
        // The stored token is a hash (64 characters for sha256)
        ->and(strlen((string) $provider->lastUpdatedRememberToken))->toBe(64);
});

test('it authenticates via remember token cookie', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = '42|' . $plainToken;

    $provider = new TestUserProvider(userByRememberToken: $user);

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
    $session = new TestSession();
    $session->set('auth_user_id', 42);
    $user = new TestUser(id: 42);

    // Simulate existing remember token
    $tokenManager = new RememberTokenManager();
    $plainToken = $tokenManager->generate();
    $hashedToken = $tokenManager->hash($plainToken);
    $user->setRememberToken($hashedToken);

    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = '42|' . $plainToken;

    $provider = new TestUserProvider(userById: $user);

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
        ->and($provider->lastUpdatedRememberToken)->toBeNull();
});

test('it regenerates remember token on each use', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);

    // Simulate a valid remember token cookie
    $tokenManager = new RememberTokenManager();
    $originalPlainToken = $tokenManager->generate();
    $originalHashedToken = $tokenManager->hash($originalPlainToken);
    $user->setRememberToken($originalHashedToken);

    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = '42|' . $originalPlainToken;
    $originalCookieValue = $cookieJar->cookies['remember_web'];

    $provider = new TestUserProvider(userByRememberToken: $user);

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
    expect($provider->lastUpdatedRememberToken)->toBeString()
        ->and($provider->lastUpdatedRememberToken)->not->toBe($originalHashedToken)
        ->and($cookieJar->cookies['remember_web'])->not->toBe($originalCookieValue);
});

test('it does not create token when remember is false', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
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
        ->and($provider->lastUpdatedRememberToken)->toBeNull();
});

test('it does not create token when remember is explicitly false', function (): void {
    $session = new TestSession();
    $user = new TestUser(id: 42);
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
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
        ->and($provider->lastUpdatedRememberToken)->toBeNull();
});

test('it handles missing remember token gracefully', function (): void {
    $session = new TestSession();
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
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
    $session = new TestSession();
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = 'invalid-format-no-pipe';
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
    $session = new TestSession();
    $user = new TestUser(id: 42);

    // Set a different token on the user (simulating mismatch)
    $tokenManager = new RememberTokenManager();
    $validToken = $tokenManager->generate();
    $user->setRememberToken($tokenManager->hash($validToken));

    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = '42|wrong_token_here';

    $provider = new TestUserProvider(userByRememberToken: $user);

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
    $session = new TestSession();
    $provider = new TestUserProvider();
    $cookieJar = new TestCookieJar();
    $cookieJar->cookies['remember_web'] = '999|some_token_value';
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
