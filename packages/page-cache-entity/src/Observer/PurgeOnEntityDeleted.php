<?php

declare(strict_types=1);

namespace Marko\PageCache\Entity\Observer;

use Marko\Core\Attributes\Observer;
use Marko\Database\Events\EntityDeleted;
use Marko\PageCache\Entity\IdentityPurger;

#[Observer(event: EntityDeleted::class)]
readonly class PurgeOnEntityDeleted
{
    public function __construct(
        private IdentityPurger $identityPurger,
    ) {}

    public function handle(EntityDeleted $event): void
    {
        $this->identityPurger->purge($event->entity);
    }
}
