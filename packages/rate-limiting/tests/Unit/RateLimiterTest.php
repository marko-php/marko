<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\RateLimiter;
use Marko\RateLimiting\RateLimitResult;

function createRateLimitCacheConfig(
    int $defaultTtl = 3600,
): CacheConfig {
    $configRepo = new readonly class ($defaultTtl) implements ConfigRepositoryInterface
    {
        public function __construct(
            private int $defaultTtl,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            return match ($key) {
                'cache.path' => '/tmp/cache',
                'cache.default_ttl' => $this->defaultTtl,
                'cache.driver' => 'array',
                default => throw new ConfigNotFoundException($key),
            };
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return in_array($key, ['cache.path', 'cache.default_ttl', 'cache.driver'], true);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return [];
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new CacheConfig($configRepo);
}

function createRateLimitTestCache(): CacheInterface
{
    return new ArrayCacheDriver(createRateLimitCacheConfig());
}

describe('RateLimiter', function (): void {
    beforeEach(function (): void {
        $this->cache = createRateLimitTestCache();
        $this->limiter = new RateLimiter($this->cache);
    });

    it('implements RateLimiterInterface', function (): void {
        expect($this->limiter)->toBeInstanceOf(RateLimiterInterface::class);
    });

    it('allows first attempt within limit', function (): void {
        $result = $this->limiter->attempt('test-key', 5, 60);

        expect($result)->toBeInstanceOf(RateLimitResult::class)
            ->and($result->allowed())->toBeTrue()
            ->and($result->remaining())->toBe(4);
    });

    it('tracks attempt count across calls', function (): void {
        $this->limiter->attempt('test-key', 5, 60);
        $this->limiter->attempt('test-key', 5, 60);
        $result = $this->limiter->attempt('test-key', 5, 60);

        expect($result->allowed())->toBeTrue()
            ->and($result->remaining())->toBe(2);
    });

    it('blocks when max attempts exceeded', function (): void {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('test-key', 3, 60);
        }

        $result = $this->limiter->attempt('test-key', 3, 60);

        expect($result->allowed())->toBeFalse()
            ->and($result->remaining())->toBe(0);
    });

    it('returns remaining attempts count', function (): void {
        $result1 = $this->limiter->attempt('test-key', 3, 60);
        $result2 = $this->limiter->attempt('test-key', 3, 60);
        $result3 = $this->limiter->attempt('test-key', 3, 60);

        expect($result1->remaining())->toBe(2)
            ->and($result2->remaining())->toBe(1)
            ->and($result3->remaining())->toBe(0);
    });

    it('returns retry after seconds when blocked', function (): void {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('test-key', 3, 60);
        }

        $result = $this->limiter->attempt('test-key', 3, 60);

        expect($result->retryAfter())->not->toBeNull()
            ->and($result->retryAfter())->toBeGreaterThanOrEqual(0)
            ->and($result->retryAfter())->toBeLessThanOrEqual(60);
    });

    it('stores cache key with TTL for decay window', function (): void {
        $this->limiter->attempt('test-key', 5, 120);

        $item = $this->cache->getItem('rate_limit.test-key');

        expect($item->isHit())->toBeTrue()
            ->and($item->expiresAt())->not->toBeNull();
    });

    it('reports too many attempts without incrementing', function (): void {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('test-key', 3, 60);
        }

        $tooMany = $this->limiter->tooManyAttempts('test-key', 3);

        expect($tooMany)->toBeTrue();

        // Verify it did not increment by checking the cache value directly
        $attempts = (int) $this->cache->get('rate_limit.test-key', 0);

        expect($attempts)->toBe(3);
    });

    it('returns false for tooManyAttempts when under limit', function (): void {
        $this->limiter->attempt('test-key', 5, 60);

        expect($this->limiter->tooManyAttempts('test-key', 5))->toBeFalse();
    });

    it('clears rate limit for a key', function (): void {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('test-key', 3, 60);
        }

        $this->limiter->clear('test-key');

        expect($this->limiter->tooManyAttempts('test-key', 3))->toBeFalse();

        $result = $this->limiter->attempt('test-key', 3, 60);

        expect($result->allowed())->toBeTrue()
            ->and($result->remaining())->toBe(2);
    });

    it('tracks separate keys independently', function (): void {
        $this->limiter->attempt('key-a', 2, 60);
        $this->limiter->attempt('key-a', 2, 60);

        $resultA = $this->limiter->attempt('key-a', 2, 60);
        $resultB = $this->limiter->attempt('key-b', 2, 60);

        expect($resultA->allowed())->toBeFalse()
            ->and($resultB->allowed())->toBeTrue()
            ->and($resultB->remaining())->toBe(1);
    });
});
