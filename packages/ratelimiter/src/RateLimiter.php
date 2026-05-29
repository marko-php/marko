<?php

declare(strict_types=1);

namespace Marko\RateLimiter;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\RateLimiter\Contracts\RateLimiterInterface;

class RateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
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
        $attempts = (int) $this->cache->get($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
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

        $this->cache->set($cacheKey, $attempts + 1, $decaySeconds);

        return new RateLimitResult(
            allowed: true,
            remaining: $maxAttempts - $attempts - 1,
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
