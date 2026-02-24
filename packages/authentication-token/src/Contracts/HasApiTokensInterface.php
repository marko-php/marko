<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Contracts;

use Marko\AuthenticationToken\Entity\PersonalAccessToken;

interface HasApiTokensInterface
{
    /**
     * @return array<PersonalAccessToken>
     */
    public function getTokens(): array;

    /**
     * @noinspection PhpUnused
     *
     * @param array<string> $abilities
     */
    public function createToken(
        string $name,
        array $abilities = [],
    ): NewAccessToken;
}
