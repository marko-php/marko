<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Sse\Exceptions\SseException;
use Marko\Sse\SseConnectionLimiter;
use Marko\Sse\Tests\Support\RecordingCache;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

describe('SseConnectionLimiter', function (): void {
    it('is not limited when max_connections is null', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: null, retryAfter: 5);

        expect($limiter->isLimited())->toBeFalse()
            ->and($cache->increments)->toBeEmpty();
    });

    it('acquires a free slot via increment', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 2, retryAfter: 5);

        $first = $limiter->acquire(ttl: 360);
        $second = $limiter->acquire(ttl: 360);

        expect($limiter->isLimited())->toBeTrue()
            ->and([$first, $second])->toEqualCanonicalizing([0, 1])
            ->and($cache->increments[0]['ttl'])->toBe(360)
            ->and($cache->increments[0]['key'])->toStartWith('sse.connection_slot.');
    });

    it('returns null when every slot is taken', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 2, retryAfter: 5);

        $limiter->acquire(ttl: 60);
        $limiter->acquire(ttl: 60);

        expect($limiter->acquire(ttl: 60))->toBeNull();
    });

    it('releases a slot by deleting its key', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);

        $slot = $limiter->acquire(ttl: 60);
        $limiter->release($slot);

        expect($cache->deletes)->toBe(['sse.connection_slot.0'])
            ->and($limiter->acquire(ttl: 60))->toBe(0);
    });

    it('exposes the retry-after delay', function (): void {
        $limiter = new SseConnectionLimiter(cache: new RecordingCache(), maxConnections: 1, retryAfter: 7);

        expect($limiter->retryAfter())->toBe(7);
    });

    it('rejects a max_connections below one', function (): void {
        expect(fn () => new SseConnectionLimiter(cache: new RecordingCache(), maxConnections: 0, retryAfter: 5))
            ->toThrow(SseException::class, 'sse.max_connections must be at least 1');
    });

    it('refuses the process-local array cache driver', function (): void {
        expect(
            fn () => new SseConnectionLimiter(
                cache: new ArrayCacheDriver(
                    new CacheConfig(new FakeConfigRepository()),
                    new FakeClock(),
                    new CacheOperations(),
                ),
                maxConnections: 10,
                retryAfter: 5,
            ),
        )
            ->toThrow(SseException::class, 'needs a cache shared across PHP processes');
    });

    it('allows the array cache driver when the guard is disabled', function (): void {
        $limiter = new SseConnectionLimiter(
            cache: new ArrayCacheDriver(
                new CacheConfig(new FakeConfigRepository()),
                new FakeClock(),
                new CacheOperations(),
            ),
            maxConnections: null,
            retryAfter: 5,
        );

        expect($limiter->isLimited())->toBeFalse();
    });

    it('is built from config/sse.php by the module binding', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $defaults = require dirname(__DIR__) . '/config/sse.php';

        $container = new Container();
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
            'sse.max_connections' => 50,
            'sse.retry_after' => $defaults['retry_after'],
        ]));
        $container->instance(CacheInterface::class, new RecordingCache());

        $limiter = $module['bindings'][SseConnectionLimiter::class]($container);

        expect($defaults['max_connections'])->toBeNull()
            ->and($limiter->isLimited())->toBeTrue()
            ->and($limiter->retryAfter())->toBe(5);
    });
});
