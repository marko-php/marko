<?php

declare(strict_types=1);

namespace Marko\Authentication\Event;

use Marko\Authentication\MustVerifyEmailInterface;
use Marko\Core\Event\Event;

/**
 * A user verified their email address. Dispatched once, after the user was saved.
 */
class EmailVerifiedEvent extends Event
{
    public function __construct(
        public readonly MustVerifyEmailInterface $user,
    ) {}

    public function getUser(): MustVerifyEmailInterface
    {
        return $this->user;
    }
}
