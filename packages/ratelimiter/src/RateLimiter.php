<?php

declare(strict_types=1);

namespace Marko\RateLimiter;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\RateLimiter\Contracts\RateLimiterInterface;

readonly class RateLimiter implements RateLimiterInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    /**
     * @throws InvalidKeyException
     */
    public function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
    ): RateLimitResult {
        $cacheKey = $this->getCacheKey($key);
        $count = $this->cache->increment($cacheKey, $decaySeconds);

        if ($count > $maxAttempts) {
            $item = $this->cache->getItem($cacheKey);
            $retryAfter = null;

            if ($item->isHit() && $item->expiresAt() !== null) {
                $retryAfter = max(0, $item->expiresAt()->getTimestamp() - time());
            }

            return new RateLimitResult(
                allowed: false,
                remaining: 0,
                retryAfter: $retryAfter ?? $decaySeconds,
            );
        }

        return new RateLimitResult(
            allowed: true,
            remaining: max(0, $maxAttempts - $count),
        );
    }

    /**
     * @throws InvalidKeyException
     */
    public function tooManyAttempts(
        string $key,
        int $maxAttempts,
    ): bool {
        $attempts = (int) $this->cache->get($this->getCacheKey($key), 0);

        return $attempts >= $maxAttempts;
    }

    /**
     * @throws InvalidKeyException
     */
    public function clear(
        string $key,
    ): void {
        $this->cache->delete($this->getCacheKey($key));
    }

    private function getCacheKey(
        string $key,
    ): string {
        return "rate_limit.$key";
    }
}
