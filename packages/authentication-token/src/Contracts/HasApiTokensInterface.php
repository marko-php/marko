<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Contracts;

interface HasApiTokensInterface
{
    /**
     * @return array<PersonalAccessTokenInterface>
     */
    public function getTokens(): array;

    /**
     * @param array<string> $abilities
     */
    public function createToken(
        string $name,
        array $abilities = [],
    ): NewAccessToken;
}
