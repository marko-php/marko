<?php

declare(strict_types=1);

namespace Marko\PageCache\Entity\Observer;

use Marko\Core\Attributes\Observer;
use Marko\Database\Events\EntityUpdated;
use Marko\PageCache\Entity\IdentityPurger;

#[Observer(event: EntityUpdated::class)]
readonly class PurgeOnEntityUpdated
{
    public function __construct(
        private IdentityPurger $identityPurger,
    ) {}

    public function handle(EntityUpdated $event): void
    {
        $this->identityPurger->purge($event->entity);
    }
}
