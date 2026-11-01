<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Event;

use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\Core\Event\Event;

/**
 * Dispatched when a user passes two-factor with one of their recovery codes.
 * The code is spent; remainingCodes tells you when to prompt for new ones.
 */
class RecoveryCodeUsedEvent extends Event
{
    public function __construct(
        public readonly TwoFactorAuthenticatableInterface $user,
        public readonly int $remainingCodes,
    ) {}

    public function getUser(): TwoFactorAuthenticatableInterface
    {
        return $this->user;
    }

    public function getRemainingCodes(): int
    {
        return $this->remainingCodes;
    }
}
