<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Event;

use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\Core\Event\Event;

/**
 * Dispatched when a user confirms their two-factor secret and it becomes active.
 */
class TwoFactorEnabledEvent extends Event
{
    public function __construct(
        public readonly TwoFactorAuthenticatableInterface $user,
    ) {}

    public function getUser(): TwoFactorAuthenticatableInterface
    {
        return $this->user;
    }
}
