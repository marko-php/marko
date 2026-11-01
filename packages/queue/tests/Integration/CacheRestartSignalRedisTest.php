<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Clock\SystemClock;
use Marko\Core\Path\ProjectPaths;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\CacheRestartSignal;
use Marko\Queue\Tests\Fixtures\IntegrationRedis;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * Runs CacheRestartSignal on marko/cache-redis against a real Redis server, with two separate
 * driver instances standing in for queue:restart on one host and queue:work on another.
 */

pest()->group('integration-services');

function createRestartSignalRedisDriver(
    string $prefix,
): RedisCacheDriver {
    return new RedisCacheDriver(
        IntegrationRedis::connectionFactory(),
        new RedisCacheConfig(new FakeConfigRepository([
            'cache-redis.connection' => IntegrationRedis::CONNECTION,
            'cache-redis.prefix' => $prefix,
        ])),
        new CacheConfig(new FakeConfigRepository([
            'cache.path' => sys_get_temp_dir(),
            'cache.default_ttl' => 60,
            'cache.driver' => 'redis',
        ]), new ProjectPaths(sys_get_temp_dir())),
        new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
            'encryption.key' => 'integration-signing-key',
        ]))),
        new SystemClock(),
        new CacheOperations(),
    );
}

describe('CacheRestartSignal against a real Redis server', function (): void {
    beforeEach(function (): void {
        if (IntegrationRedis::unavailable()) {
            return;
        }

        $this->prefix = 'marko:test:restart:' . bin2hex(random_bytes(6)) . ':';
        $this->restartHost = createRestartSignalRedisDriver($this->prefix);
        $this->workerHost = createRestartSignalRedisDriver($this->prefix);
    });

    afterEach(function (): void {
        if (isset($this->restartHost)) {
            $this->restartHost->clear();
        }
    });

    it('lets a worker on another host see the token queue:restart stored', function (): void {
        $worker = new CacheRestartSignal($this->workerHost);
        $before = $worker->current();

        new CacheRestartSignal($this->restartHost)->request();

        expect($before)->toBeNull()
            ->and($worker->current())->toBeString()->not->toBeEmpty();
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());

    it('changes the token a worker sees on every restart', function (): void {
        $restart = new CacheRestartSignal($this->restartHost);
        $worker = new CacheRestartSignal($this->workerHost);

        $restart->request();
        $first = $worker->current();
        $restart->request();

        expect($worker->current())->not->toBe($first);
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());

    it('stores the token in redis with no expiry', function (): void {
        new CacheRestartSignal($this->restartHost)->request();

        $connection = IntegrationRedis::connectionFactory()->connection(IntegrationRedis::CONNECTION);

        expect($connection->client()->ttl($this->prefix . CacheRestartSignal::KEY))->toBe(-1);
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());
});
