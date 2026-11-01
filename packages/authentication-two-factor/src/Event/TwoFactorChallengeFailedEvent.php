<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Event;

use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;
use Marko\Core\Event\Event;

/**
 * Dispatched when a user waiting on the two-factor login challenge submits a
 * wrong TOTP or recovery code.
 */
class TwoFactorChallengeFailedEvent extends Event
{
    public function __construct(
        public readonly TwoFactorAuthenticatableInterface $user,
        public readonly string $guard,
    ) {}

    public function getUser(): TwoFactorAuthenticatableInterface
    {
        return $this->user;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }
}
