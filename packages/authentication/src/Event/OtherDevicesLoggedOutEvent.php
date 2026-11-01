<?php

declare(strict_types=1);

namespace Marko\Authentication\Event;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Core\Event\Event;

/**
 * A user's other sessions and remember-me devices were ended on a guard.
 */
class OtherDevicesLoggedOutEvent extends Event
{
    public function __construct(
        public readonly AuthenticatableInterface $user,
        public readonly string $guard,
    ) {}

    public function getUser(): AuthenticatableInterface
    {
        return $this->user;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }
}
