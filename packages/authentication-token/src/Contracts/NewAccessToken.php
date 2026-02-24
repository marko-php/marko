<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Contracts;

use Marko\AuthenticationToken\Entity\PersonalAccessToken;

readonly class NewAccessToken
{
    public function __construct(
        public PersonalAccessToken $accessToken,
        public string $plainTextToken,
    ) {}
}
