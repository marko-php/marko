<?php

declare(strict_types=1);

namespace Marko\PageCache\Attributes;

use Attribute;
use Marko\PageCache\Contracts\CacheTagProviderInterface;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    /**
     * @param array<string> $tags
     * @param class-string<CacheTagProviderInterface>|null $provider
     */
    public function __construct(
        public int $ttl,
        public array $tags = [],
        public ?string $provider = null,
    ) {}
}
