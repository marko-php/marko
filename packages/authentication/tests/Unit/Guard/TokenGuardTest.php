<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Unit\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Guard\TokenGuard;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeUserProvider;
use ReflectionClass;

test('it implements GuardInterface', function (): void {
    expect(class_exists(TokenGuard::class))->toBeTrue()
        ->and(in_array(GuardInterface::class, class_implements(TokenGuard::class), true))->toBeTrue();
});

test('it extracts token from Authorization header', function (): void {
    $guard = new TokenGuard();

    $headers = ['Authorization' => 'Bearer test-token-123'];
    $token = $guard->getTokenFromHeaders($headers);

    expect($token)->toBe('test-token-123');
});

test('it strips Bearer prefix from token', function (): void {
    $guard = new TokenGuard();

    // The token should be returned without the Bearer prefix
    $headers = ['Authorization' => 'Bearer my-api-token-abc'];
    $token = $guard->getTokenFromHeaders($headers);

    expect($token)->not->toContain('Bearer')
        ->and($token)->toBe('my-api-token-abc');
});

test('it returns user for valid token', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $provider = new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private AuthenticatableInterface $user,
        ) {}

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            if (isset($credentials['api_token']) && $credentials['api_token'] === 'valid-token') {
                return $this->user;
            }

            return null;
        }

        public function validateCredentials(
            AuthenticatableInterface $user,
            array $credentials,
        ): bool {
            return true;
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

    $guard = new TokenGuard();
    $guard->provider = $provider;

    $headers = ['Authorization' => 'Bearer valid-token'];
    $guard->setHeaders($headers);

    expect($guard->user())->toBe($user);
});

test('it returns null for invalid token', function (): void {
    $provider = new FakeUserProvider();

    $guard = new TokenGuard();
    $guard->provider = $provider;

    $headers = ['Authorization' => 'Bearer invalid-token'];
    $guard->setHeaders($headers);

    expect($guard->user())->toBeNull();
});

test('it returns true from check when token valid', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $provider = new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private AuthenticatableInterface $user,
        ) {}

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            if (isset($credentials['api_token']) && $credentials['api_token'] === 'valid-token') {
                return $this->user;
            }

            return null;
        }

        public function validateCredentials(
            AuthenticatableInterface $user,
            array $credentials,
        ): bool {
            return true;
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

    $guard = new TokenGuard();
    $guard->provider = $provider;

    $headers = ['Authorization' => 'Bearer valid-token'];
    $guard->setHeaders($headers);

    expect($guard->check())->toBeTrue();
});

test('it returns false from check when no token', function (): void {
    $guard = new TokenGuard();

    // No headers set, no token
    expect($guard->check())->toBeFalse();
});

test('it returns null when Authorization header is missing', function (): void {
    $guard = new TokenGuard();

    // Headers without Authorization
    $headers = ['Content-Type' => 'application/json'];
    $token = $guard->getTokenFromHeaders($headers);

    expect($token)->toBeNull();
});

test('it returns null when headers array is empty', function (): void {
    $guard = new TokenGuard();

    $token = $guard->getTokenFromHeaders([]);

    expect($token)->toBeNull();
});

test('it supports configurable header name', function (): void {
    $guard = new TokenGuard(
        headerName: 'X-API-Key',
    );

    $headers = ['X-API-Key' => 'Bearer my-custom-token'];
    $token = $guard->getTokenFromHeaders($headers);

    expect($token)->toBe('my-custom-token');
});

test('it supports configurable prefix', function (): void {
    $guard = new TokenGuard(
        prefix: 'Token ',
    );

    $headers = ['Authorization' => 'Token my-custom-token'];
    $token = $guard->getTokenFromHeaders($headers);

    expect($token)->toBe('my-custom-token');
});

test('it handles logout as no-op for stateless token auth', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $provider = new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private AuthenticatableInterface $user,
        ) {}

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return null;
        }

        public function retrieveByCredentials(
            array $credentials,
        ): ?AuthenticatableInterface {
            if (isset($credentials['api_token']) && $credentials['api_token'] === 'valid-token') {
                return $this->user;
            }

            return null;
        }

        public function validateCredentials(
            AuthenticatableInterface $user,
            array $credentials,
        ): bool {
            return true;
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

    $guard = new TokenGuard();
    $guard->provider = $provider;
    $guard->setHeaders(['Authorization' => 'Bearer valid-token']);

    // Verify user is authenticated
    expect($guard->check())->toBeTrue();

    // Logout should be a no-op for stateless token auth
    $guard->logout();

    // Token auth is stateless - logout doesn't affect authentication
    // Re-setting headers should still work
    $guard->setHeaders(['Authorization' => 'Bearer valid-token']);
    expect($guard->check())->toBeTrue();
});

test('it is stateless (no session dependency)', function (): void {
    $reflection = new ReflectionClass(TokenGuard::class);

    // Verify the class doesn't have any session-related properties or constructor parameters
    $constructor = $reflection->getConstructor();
    $parameters = $constructor->getParameters();

    $parameterNames = array_map(fn ($p) => $p->getName(), $parameters);

    // Should not require session as a dependency
    expect($parameterNames)->not->toContain('session')
        ->and($parameterNames)->not->toContain('sessionDriver')
        ->and($parameterNames)->not->toContain('sessionHandler');

    // Verify no session property
    $properties = $reflection->getProperties();
    $propertyNames = array_map(fn ($p) => $p->getName(), $properties);

    expect($propertyNames)->not->toContain('session')
        ->and($propertyNames)->not->toContain('sessionId');

    // Verify login/logout are no-ops (stateless means no state to persist)
    $user = new FakeAuthenticatable(id: 1);

    $guard = new TokenGuard();

    // login and logout should not throw - they are no-ops for stateless guards
    $guard->login($user);
    $guard->logout();

    // Authentication should still be determined by headers, not login state
    expect($guard->check())->toBeFalse();
});
