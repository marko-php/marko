<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Fixtures;

use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;

/**
 * Token storage held in memory, standing in for an application's database
 * repository in end-to-end tests.
 */
class InMemoryTokenRepository implements TokenRepositoryInterface
{
    /** @var array<int, PersonalAccessToken> */
    private array $tokens = [];

    public function find(
        int $id,
    ): ?PersonalAccessToken {
        return $this->tokens[$id] ?? null;
    }

    public function findByToken(
        string $tokenHash,
    ): ?PersonalAccessToken {
        return array_find(
            $this->tokens,
            fn (PersonalAccessToken $token): bool => hash_equals($token->tokenHash, $tokenHash),
        );
    }

    public function create(
        PersonalAccessToken $token,
    ): PersonalAccessToken {
        $token->id = count($this->tokens) + 1;
        $this->tokens[$token->id] = $token;

        return $token;
    }

    public function revoke(
        int $id,
    ): void {
        unset($this->tokens[$id]);
    }

    public function revokeAllForUser(
        string $type,
        int|string $id,
    ): void {
        $this->tokens = array_filter(
            $this->tokens,
            fn (PersonalAccessToken $token): bool => $token->tokenableType !== $type
                || $token->tokenableId !== $id,
        );
    }
}
