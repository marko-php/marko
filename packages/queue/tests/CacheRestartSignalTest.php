<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Queue\CacheRestartSignal;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\RestartSignalInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

function createArrayRestartCache(): ArrayCacheDriver
{
    return new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
        'cache.path' => sys_get_temp_dir(),
        'cache.default_ttl' => 60,
        'cache.driver' => 'array',
    ])), new FakeClock(), new CacheOperations());
}

describe('CacheRestartSignal', function (): void {
    it('implements RestartSignalInterface', function (): void {
        expect(new CacheRestartSignal(createArrayRestartCache()))->toBeInstanceOf(RestartSignalInterface::class);
    });

    it('returns null before any restart was requested', function (): void {
        expect(new CacheRestartSignal(createArrayRestartCache())->current())->toBeNull();
    });

    it('stores a new token that another instance on the same cache reads', function (): void {
        $cache = createArrayRestartCache();
        $writer = new CacheRestartSignal($cache);
        $reader = new CacheRestartSignal($cache);

        $writer->request();

        expect($reader->current())->toBeString()->not->toBeEmpty()
            ->and($reader->current())->toBe($writer->current());
    });

    it('stores a different token on every request', function (): void {
        $signal = new CacheRestartSignal(createArrayRestartCache());

        $signal->request();
        $first = $signal->current();
        $signal->request();

        expect($signal->current())->not->toBe($first);
    });

    it('stores the token without an expiry, so it outlives the default ttl', function (): void {
        $clock = new FakeClock();
        $cache = new ArrayCacheDriver(new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 60,
            'cache.driver' => 'array',
        ])), $clock, new CacheOperations());
        $signal = new CacheRestartSignal($cache);

        $signal->request();
        $token = $signal->current();
        $clock->travel('+30 days');

        expect($signal->current())->toBe($token)
            ->and($cache->getItem(CacheRestartSignal::KEY)->expiresAt())->toBeNull();
    });

    it('throws when the cache refuses to store the token', function (): void {
        $cache = new class (new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 60,
            'cache.driver' => 'array',
        ])), new FakeClock(), new CacheOperations()) extends ArrayCacheDriver
        {
            public function forever(
                string $key,
                mixed $value,
            ): bool {
                return false;
            }
        };

        try {
            new CacheRestartSignal($cache)->request();
            $this->fail('A refused write must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain(CacheRestartSignal::KEY)
                ->and($e->getSuggestion())->toContain('cache');
        }
    });

    it('throws when the cache holds something other than a token string', function (): void {
        $cache = createArrayRestartCache();
        $cache->forever(CacheRestartSignal::KEY, ['not', 'a', 'token']);

        try {
            new CacheRestartSignal($cache)->current();
            $this->fail('A non-string token must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain(CacheRestartSignal::KEY)
                ->and($e->getContext())->toContain('array');
        }
    });
});
