<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Events;

use DateTimeImmutable;
use Marko\Core\Event\Event;

class PermissionsSynced extends Event
{
    public function __construct(
        private readonly int $createdCount,
        private readonly int $totalCount,
        private readonly DateTimeImmutable $timestamp = new DateTimeImmutable(),
    ) {}

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }
}
