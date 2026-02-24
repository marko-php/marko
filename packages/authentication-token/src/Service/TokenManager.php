<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Service;

use Marko\Authentication\AuthenticatableInterface;
use Marko\AuthenticationToken\Contracts\NewAccessToken;
use Marko\AuthenticationToken\Contracts\TokenRepositoryInterface;
use Marko\AuthenticationToken\Entity\PersonalAccessToken;

readonly class TokenManager
{
    public function __construct(
        private TokenRepositoryInterface $repository,
    ) {}

    public function createToken(
        AuthenticatableInterface $user,
        string $name,
        array $abilities = [],
    ): NewAccessToken {
        $rawToken = bin2hex(random_bytes(40));
        $tokenHash = hash('sha256', $rawToken);

        $token = new PersonalAccessToken();
        $token->tokenableType = get_class($user);
        $token->tokenableId = $user->getAuthIdentifier();
        $token->name = $name;
        $token->tokenHash = $tokenHash;
        $token->abilities = json_encode($abilities);

        $saved = $this->repository->create($token);

        return new NewAccessToken($saved, $rawToken);
    }

    public function revokeToken(
        int $tokenId,
    ): void {
        $this->repository->revoke($tokenId);
    }

    public function revokeAllTokens(
        AuthenticatableInterface $user,
    ): void {
        $this->repository->revokeAllForUser(
            get_class($user),
            $user->getAuthIdentifier(),
        );
    }
}
