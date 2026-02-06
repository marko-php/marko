<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Events;

use DateTimeImmutable;
use Marko\AdminAuth\Entity\RoleInterface;
use Marko\Core\Event\Event;

class RoleDeleted extends Event
{
    public function __construct(
        private readonly RoleInterface $role,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getRole(): RoleInterface
    {
        return $this->role;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
