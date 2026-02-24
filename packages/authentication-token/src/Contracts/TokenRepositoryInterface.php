<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Contracts;

use Marko\AuthenticationToken\Entity\PersonalAccessToken;

interface TokenRepositoryInterface
{
    public function find(
        int $id,
    ): ?PersonalAccessToken;

    public function findByToken(
        string $tokenHash,
    ): ?PersonalAccessToken;

    public function create(
        PersonalAccessToken $token,
    ): PersonalAccessToken;

    public function revoke(
        int $id,
    ): void;

    public function revokeAllForUser(
        string $type,
        int|string $id,
    ): void;
}
