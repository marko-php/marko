<?php

declare(strict_types=1);

namespace Marko\PageCache\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        public int $ttl,
        public array $tags = [],
    ) {}
}
