<?php

declare(strict_types=1);

namespace Marko\Auth\Event;

use Marko\Auth\AuthenticatableInterface;
use Marko\Core\Event\Event;

class LoginEvent extends Event
{
    public function __construct(
        public readonly AuthenticatableInterface $user,
        public readonly string $guard,
        public readonly bool $remember = false,
    ) {}

    public function getUser(): AuthenticatableInterface
    {
        return $this->user;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }

    public function getRemember(): bool
    {
        return $this->remember;
    }
}
