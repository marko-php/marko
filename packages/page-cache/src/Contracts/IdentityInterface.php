<?php

declare(strict_types=1);

namespace Marko\PageCache\Contracts;

interface IdentityInterface
{
    /**
     * Cache tags this object owns. Returned tags will be purged from the
     * page cache when an observer determines this object has changed.
     *
     * @return array<string>
     */
    public function getIdentities(): array;
}
