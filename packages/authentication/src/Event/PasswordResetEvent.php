<?php

declare(strict_types=1);

namespace Marko\Authentication\Event;

use Marko\Authentication\AuthenticatableInterface;
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
