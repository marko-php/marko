<?php

declare(strict_types=1);

namespace Marko\Authorization;

use Marko\Authentication\AuthenticatableInterface;

interface AuthorizableInterface extends AuthenticatableInterface
{
    /**
     * Check if the user has the given ability.
     */
    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool;
}
