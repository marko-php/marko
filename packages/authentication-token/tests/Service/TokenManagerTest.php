<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Service;

use Marko\AuthenticationToken\Contracts\NewAccessToken;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;
use Marko\AuthenticationToken\Service\TokenManager;
use Marko\Testing\Fake\FakeAuthenticatable;

class FakeTokenRepository implements TokenRepositoryInterface
{
    /** @var list<PersonalAccessToken> */
    public array $created = [];

    /** @var list<int> */
    public array $revoked = [];

    /** @var array<string, list<int|string>> */
    public array $revokedForUser = [];

    public function find(
        int $id,
    ): ?PersonalAccessToken {
        return null;
    }

    public function findByToken(
        string $tokenHash,
    ): ?PersonalAccessToken {
        return null;
    }

    public function create(
        PersonalAccessToken $token,
    ): PersonalAccessToken {
        $this->created[] = $token;

        return $token;
    }

    public function revoke(
        int $id,
    ): void {
        $this->revoked[] = $id;
    }

    public function revokeAllForUser(
        string $type,
        int|string $id,
    ): void {
        $this->revokedForUser[$type][] = $id;
    }
}

it('creates a new personal access token with SHA-256 hashed storage', function (): void {
    $repository = new FakeTokenRepository();
    $manager = new TokenManager($repository);
    $user = new FakeAuthenticatable(id: 1);

    $result = $manager->createToken($user, 'My Token');

    expect($repository->created)->toHaveCount(1)
        ->and($repository->created[0]->tokenHash)->toBe(hash('sha256', $result->plainTextToken));
});

it('returns NewAccessToken value object with plain-text token at creation time', function (): void {
    $repository = new FakeTokenRepository();
    $manager = new TokenManager($repository);
    $user = new FakeAuthenticatable(id: 1);

    $result = $manager->createToken($user, 'Test Token');

    expect($result)->toBeInstanceOf(NewAccessToken::class)
        ->and($result->plainTextToken)->not->toBeEmpty()
        ->and(strlen($result->plainTextToken))->toBe(80);
});

it('assigns abilities array to created token', function (): void {
    $repository = new FakeTokenRepository();
    $manager = new TokenManager($repository);
    $user = new FakeAuthenticatable(id: 1);

    $manager->createToken($user, 'Admin Token', ['read', 'write', 'delete']);

    expect($repository->created)->toHaveCount(1)
        ->and($repository->created[0]->abilities)->toBe(json_encode(['read', 'write', 'delete']));
});

it('revokes a token by its id', function (): void {
    $repository = new FakeTokenRepository();
    $manager = new TokenManager($repository);

    $manager->revokeToken(42);

    expect($repository->revoked)->toHaveCount(1)
        ->and($repository->revoked[0])->toBe(42);
});

it('revokes all tokens for a user', function (): void {
    $repository = new FakeTokenRepository();
    $manager = new TokenManager($repository);
    $user = new FakeAuthenticatable(id: 7);

    $manager->revokeAllTokens($user);

    expect($repository->revokedForUser)->toHaveKey(FakeAuthenticatable::class)
        ->and($repository->revokedForUser[FakeAuthenticatable::class])->toContain(7);
});
