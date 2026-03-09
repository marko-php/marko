<?php

declare(strict_types=1);

namespace Marko\Database\Events;

use Marko\Core\Event\Event;
use Marko\Database\Entity\Entity;

abstract class EntityLifecycleEvent extends Event
{
    public function __construct(
        public readonly Entity $entity,
        public readonly string $entityClass,
    ) {}
}
