<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Guard;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\AuthenticationToken\Guard\TokenGuard;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeAuthenticatable;

function makeRequest(
    string $authHeader = '',
): Request {
    $server = [];

    if ($authHeader !== '') {
        $server['HTTP_AUTHORIZATION'] = $authHeader;
    }

    return new Request(server: $server);
}

function makeRepository(
    ?PersonalAccessToken $token = null,
): TokenRepositoryInterface {
    return new readonly class ($token) implements TokenRepositoryInterface
    {
        public function __construct(
            private ?PersonalAccessToken $token,
        ) {}

        public function find(
            int $id,
        ): ?PersonalAccessToken {
            return null;
        }

        public function findByToken(
            string $tokenHash,
        ): ?PersonalAccessToken {
            return $this->token;
        }

        public function create(
            PersonalAccessToken $token,
        ): PersonalAccessToken {
            return $token;
        }

        public function revoke(
            int $id,
        ): void {}

        public function revokeAllForUser(
            string $type,
            int|string $id,
        ): void {}
    };
}

function makeUserProvider(
    ?AuthenticatableInterface $user = null,
): UserProviderInterface {
    return new readonly class ($user) implements UserProviderInterface
    {
        public function __construct(
            private ?AuthenticatableInterface $user,
        ) {}

        public function retrieveById(
            int|string $identifier,
        ): ?AuthenticatableInterface {
            return $this->user;
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
}

it('implements GuardInterface from marko/authentication', function (): void {
    expect(class_exists(TokenGuard::class))->toBeTrue()
        ->and(in_array(GuardInterface::class, class_implements(TokenGuard::class), true))->toBeTrue();
});

it('extracts Bearer token from Authorization header', function (): void {
    $repository = makeRepository();
    $request = makeRequest('Bearer my-secret-token');
    $guard = new TokenGuard($repository, $request);

    expect($guard->extractToken())->toBe('my-secret-token');
});

it('returns null user when no Authorization header is present', function (): void {
    $repository = makeRepository();
    $request = makeRequest(); // no auth header
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider();

    expect($guard->user())->toBeNull();
});

it('returns null user when token is not found or revoked', function (): void {
    $repository = makeRepository(); // no token found
    $request = makeRequest('Bearer some-revoked-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider();

    expect($guard->user())->toBeNull();
});

it('checks token abilities for fine-grained authorization', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $token = new PersonalAccessToken();
    $token->tokenableId = 1;
    $token->tokenableType = FakeAuthenticatable::class;
    $token->abilities = json_encode(['read', 'write']);

    $repository = makeRepository($token);
    $request = makeRequest('Bearer valid-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);

    expect($guard->hasAbility('read'))->toBeTrue()
        ->and($guard->hasAbility('write'))->toBeTrue()
        ->and($guard->hasAbility('delete'))->toBeFalse();
});

it('authenticates user by hashing token and looking up in repository', function (): void {
    $user = new FakeAuthenticatable(id: 42);
    $rawToken = 'plain-text-token';

    $token = new PersonalAccessToken();
    $token->tokenableId = 42;
    $token->tokenableType = FakeAuthenticatable::class;

    $repository = makeRepository($token);
    $provider = makeUserProvider($user);

    $request = makeRequest('Bearer ' . $rawToken);
    $guard = new TokenGuard($repository, $request);
    $guard->provider = $provider;

    $authenticatedUser = $guard->user();

    expect($authenticatedUser)->toBe($user);
});

it('resolves the user for a token whose expiresAt is null', function (): void {
    $user = new FakeAuthenticatable(id: 1);

    $token = new PersonalAccessToken();
    $token->tokenableId = 1;
    $token->tokenableType = FakeAuthenticatable::class;
    $token->expiresAt = null;

    $repository = makeRepository($token);
    $request = makeRequest('Bearer valid-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);

    expect($guard->user())->toBe($user);
});

it('resolves the user for a token whose expiresAt is in the future', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $future = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $token = new PersonalAccessToken();
    $token->tokenableId = 1;
    $token->tokenableType = FakeAuthenticatable::class;
    $token->expiresAt = $future;

    $repository = makeRepository($token);
    $request = makeRequest('Bearer valid-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);

    expect($guard->user())->toBe($user);
});

it('treats a token whose expiresAt is in the past as unauthenticated and returns no user', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $past = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');

    $token = new PersonalAccessToken();
    $token->tokenableId = 1;
    $token->tokenableType = FakeAuthenticatable::class;
    $token->expiresAt = $past;

    $repository = makeRepository($token);
    $request = makeRequest('Bearer expired-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);

    expect($guard->user())->toBeNull();
});

it('returns false from hasAbility when the resolved token has expired', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $past = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');

    $token = new PersonalAccessToken();
    $token->tokenableId = 1;
    $token->tokenableType = FakeAuthenticatable::class;
    $token->abilities = json_encode(['read']);
    $token->expiresAt = $past;

    $repository = makeRepository($token);
    $request = makeRequest('Bearer expired-token');
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);

    expect($guard->hasAbility('read'))->toBeFalse();
});

it('preserves the timing-safe SHA-256 hash lookup when resolving a token', function (): void {
    $user = new FakeAuthenticatable(id: 1);
    $rawToken = 'my-raw-token';
    $expectedHash = hash('sha256', $rawToken);

    $capturedHash = null;
    $repository = new class ($capturedHash) implements TokenRepositoryInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property captures hash */
            private ?string &$capturedHash,
        ) {}

        public function find(int $id): ?PersonalAccessToken
        {
            return null;
        }

        public function findByToken(string $tokenHash): ?PersonalAccessToken
        {
            $this->capturedHash = $tokenHash;
            $token = new PersonalAccessToken();
            $token->tokenableId = 1;
            $token->tokenableType = FakeAuthenticatable::class;

            return $token;
        }

        public function create(PersonalAccessToken $token): PersonalAccessToken
        {
            return $token;
        }

        public function revoke(int $id): void {}

        public function revokeAllForUser(
            string $type,
            int|string $id,
        ): void {}
    };

    $request = makeRequest('Bearer ' . $rawToken);
    $guard = new TokenGuard($repository, $request);
    $guard->provider = makeUserProvider($user);
    $guard->user();

    expect($capturedHash)->toBe($expectedHash);
});
