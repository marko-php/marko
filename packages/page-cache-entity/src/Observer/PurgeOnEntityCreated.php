<?php

declare(strict_types=1);

namespace Marko\PageCache\Entity\Observer;

use Marko\Core\Attributes\Observer;
use Marko\Database\Events\EntityCreated;
use Marko\PageCache\Entity\IdentityPurger;

#[Observer(event: EntityCreated::class)]
readonly class PurgeOnEntityCreated
{
    public function __construct(
        private IdentityPurger $identityPurger,
    ) {}

    public function handle(EntityCreated $event): void
    {
        $this->identityPurger->purge($event->entity);
    }
}
