<?php

declare(strict_types=1);

namespace Marko\Forms\Tests\Fixtures;

use Marko\Security\Contracts\CsrfTokenManagerInterface;

/**
 * Hands out one fixed token, which contains HTML special characters so tests
 * can prove the token is escaped inside the hidden input.
 */
class FixedCsrfTokenManager implements CsrfTokenManagerInterface
{
    public function __construct(
        private readonly string $token = 'tok"<&>en',
    ) {}

    public function get(): string
    {
        return $this->token;
    }

    public function validate(
        string $token,
    ): bool {
        return hash_equals($this->token, $token);
    }

    public function regenerate(): string
    {
        return $this->token;
    }
}
