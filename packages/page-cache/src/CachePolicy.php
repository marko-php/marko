<?php

declare(strict_types=1);

namespace Marko\PageCache;

readonly class CachePolicy
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public int $ttl,
        public array $tags,
    ) {}
}
