<?php

declare(strict_types=1);

namespace Marko\PageCache\Attributes;

use Attribute;
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\PageCache\Exceptions\PageCacheException;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class Cacheable
{
    /**
     * @param array<string> $tags
     * @param int $ttl Seconds to cache the page; 0 falls back to page-cache.default_ttl
     * @param class-string<CacheTagProviderInterface>|null $provider
     * @param array<string> $query Query parameter names that vary the cached page; all others are ignored
     *
     * @throws PageCacheException
     */
    public function __construct(
        public int $ttl,
        public array $tags = [],
        public ?string $provider = null,
        public array $query = [],
    ) {
        if ($ttl < 0) {
            throw PageCacheException::negativeTtl($ttl);
        }

        foreach ($query as $param) {
            if (
                !is_string($param)
                || $param === ''
            ) {
                throw PageCacheException::invalidQueryParam($param);
            }
        }
    }
}
