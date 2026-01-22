<?php

declare(strict_types=1);

namespace Marko\Auth\Event;

use Marko\Auth\AuthenticatableInterface;
use Marko\Core\Event\Event;

class PasswordResetEvent extends Event
{
    public function __construct(
        public readonly AuthenticatableInterface $user,
    ) {}

    public function getUser(): AuthenticatableInterface
    {
        return $this->user;
    }
}
