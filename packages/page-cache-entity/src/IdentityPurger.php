<?php

declare(strict_types=1);

namespace Marko\PageCache\Entity;

use Marko\Database\Entity\Entity;
use Marko\PageCache\Contracts\IdentityInterface;
use Marko\PageCache\Contracts\PageCacheInterface;

readonly class IdentityPurger
{
    public function __construct(
        private PageCacheInterface $pageCache,
    ) {}

    public function purge(Entity $entity): void
    {
        if (!$entity instanceof IdentityInterface) {
            return;
        }

        foreach ($entity->getIdentities() as $tag) {
            $this->pageCache->purgeTag($tag);
        }
    }
}
