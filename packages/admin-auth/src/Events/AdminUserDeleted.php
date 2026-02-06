<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Events;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\AdminUserInterface;
use Marko\Core\Event\Event;

class AdminUserDeleted extends Event
{
    public function __construct(
        private readonly AdminUserInterface $user,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getUser(): AdminUserInterface
    {
        return $this->user;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
