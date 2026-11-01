<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Exceptions\CacheException;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\RateLimiter;
use Marko\RateLimiter\RateLimitResult;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Psr\Clock\ClockInterface;

function createRateLimitCacheConfig(
    int $defaultTtl = 3600,
): CacheConfig {
    $config = new FakeConfigRepository([
        'cache.path' => '/tmp/cache',
        'cache.default_ttl' => $defaultTtl,
        'cache.driver' => 'array',
    ]);

    return new CacheConfig($config);
}

function createRateLimitTestCache(
    ClockInterface $clock,
): CacheInterface {
    return new ArrayCacheDriver(createRateLimitCacheConfig(), $clock, new CacheOperations());
}

describe('RateLimiter', function (): void {
    beforeEach(function (): void {
        $this->clock = new FakeClock('2026-01-01 12:00:00 UTC');
        $this->cache = createRateLimitTestCache($this->clock);
        $this->limiter = new RateLimiter($this->cache, $this->clock);
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

        $item = $this->cache->getItem('rate_limit.' . hash('xxh128', 'test-key'));

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
        $attempts = $this->cache->get('rate_limit.' . hash('xxh128', 'test-key'), 0);

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

    it('uses FakeConfigRepository instead of inline config stub in RateLimiterTest', function (): void {
        $config = new FakeConfigRepository([
            'cache.path' => '/tmp/cache',
            'cache.default_ttl' => 3600,
            'cache.driver' => 'array',
        ]);

        expect($config)->toBeInstanceOf(FakeConfigRepository::class)
            ->and($config->get('cache.driver'))->toBe('array')
            ->and($config->get('cache.default_ttl'))->toBe(3600)
            ->and($config->get('cache.path'))->toBe('/tmp/cache');
    });

    it('blocks the request once attempts reach maxAttempts', function (): void {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('test-key', 5, 60);
        }

        $result = $this->limiter->attempt('test-key', 5, 60);

        expect($result->allowed())->toBeFalse()
            ->and($result->remaining())->toBe(0);
    });

    it('reports remaining attempts decreasing across successive attempts', function (): void {
        $result1 = $this->limiter->attempt('test-key', 5, 60);
        $result2 = $this->limiter->attempt('test-key', 5, 60);
        $result3 = $this->limiter->attempt('test-key', 5, 60);

        expect($result1->remaining())->toBe(4)
            ->and($result2->remaining())->toBe(3)
            ->and($result3->remaining())->toBe(2);
    });

    it('limits an IPv6 key without throwing InvalidKeyException', function (): void {
        $first = $this->limiter->attempt('2001:db8::1', 1, 60);
        $second = $this->limiter->attempt('2001:db8::1', 1, 60);

        expect($first->allowed())->toBeTrue()
            ->and($second->allowed())->toBeFalse()
            ->and($second->retryAfter())->toBeGreaterThan(0);
    });

    it('hashes the caller key into a cache-safe key', function (): void {
        $this->limiter->attempt('login|user@example.com:{x}/y', 5, 60);

        expect($this->cache->has('rate_limit.' . hash('xxh128', 'login|user@example.com:{x}/y')))->toBeTrue();
    });

    it('reports tooManyAttempts and clears for an IPv6 key', function (): void {
        $this->limiter->attempt('2001:db8::1', 1, 60);

        expect($this->limiter->tooManyAttempts('2001:db8::1', 1))->toBeTrue();

        $this->limiter->clear('2001:db8::1');

        expect($this->limiter->tooManyAttempts('2001:db8::1', 1))->toBeFalse();
    });

    it('increments attempts atomically via the cache increment on attempt()', function (): void {
        $incrementCalled = false;
        $incrementKey = null;

        $cache = new class ($incrementCalled, $incrementKey, $this->clock) extends ArrayCacheDriver
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private bool &$incrementCalled,
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private ?string &$incrementKey,
                ClockInterface $clock,
            ) {
                parent::__construct(createRateLimitCacheConfig(), $clock, new CacheOperations());
            }

            public function increment(
                string $key,
                int $ttl,
            ): int {
                $this->incrementCalled = true;
                $this->incrementKey = $key;

                return parent::increment($key, $ttl);
            }
        };

        $limiter = new RateLimiter($cache, $this->clock);
        $limiter->attempt('test-key', 5, 60);

        expect($incrementCalled)->toBeTrue()
            ->and($incrementKey)->toBe('rate_limit.' . hash('xxh128', 'test-key'));
    });
    it('propagates a cache increment failure instead of allowing the attempt', function (): void {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('increment')
            ->willThrowException(new CacheException('Cache entry could not be opened'));
        $limiter = new RateLimiter($cache, $this->clock);

        expect(fn (): RateLimitResult => $limiter->attempt('test-key', 5, 60))
            ->toThrow(CacheException::class, 'Cache entry could not be opened');
    });

    it('computes retry after from the clock', function (): void {
        $this->limiter->attempt('clock-key', 1, 60);

        $this->clock->travel('+20 seconds');

        expect($this->limiter->attempt('clock-key', 1, 60)->retryAfter())->toBe(40);
    });

    it('counts retry after down as the clock advances', function (): void {
        $this->limiter->attempt('clock-key', 1, 60);
        $this->clock->travel('+1 second');
        $first = $this->limiter->attempt('clock-key', 1, 60)->retryAfter();

        $this->clock->travel('+58 seconds');
        $second = $this->limiter->attempt('clock-key', 1, 60)->retryAfter();

        expect($first)->toBe(59)
            ->and($second)->toBe(1);
    });

    it('reports zero retry after on the last second of the window', function (): void {
        $this->limiter->attempt('clock-key', 1, 60);

        $this->clock->travel('+60 seconds');

        $result = $this->limiter->attempt('clock-key', 1, 60);

        expect($result->allowed())->toBeFalse()
            ->and($result->retryAfter())->toBe(0);
    });

    it('allows attempts again once the clock passes the decay window', function (): void {
        $this->limiter->attempt('clock-key', 1, 60);
        expect($this->limiter->attempt('clock-key', 1, 60)->allowed())->toBeFalse();

        $this->clock->travel('+61 seconds');

        expect($this->limiter->attempt('clock-key', 1, 60)->allowed())->toBeTrue()
            ->and($this->limiter->tooManyAttempts('clock-key', 2))->toBeFalse();
    });
});
