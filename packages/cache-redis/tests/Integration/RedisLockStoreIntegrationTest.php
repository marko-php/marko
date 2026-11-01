<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\LockScope;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Lock\RedisLockStore;
use Marko\Cache\Redis\Tests\Fixtures\IntegrationRedis;
use Marko\Cache\Tests\Support\LockStoreContract;
use Marko\Cache\Tests\Support\LockStoreHarness;
use Marko\Clock\SystemClock;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Redis\RedisConnectionInterface;
use Marko\Testing\Fake\FakeConfigRepository;

/*
 * Runs RedisLockStore against a real Redis server (see IntegrationRedis for the
 * environment variables). Part of the integration-services group; the Tests job
 * runs it too, against its own Redis service.
 */

pest()->group('integration-services');

function redisLockIntegrationStore(
    string $prefix,
): RedisLockStore {
    return new RedisLockStore(
        IntegrationRedis::connectionFactory(),
        new RedisCacheConfig(new FakeConfigRepository([
            'cache-redis.connection' => IntegrationRedis::CONNECTION,
            'cache-redis.prefix' => $prefix,
        ])),
    );
}

function redisLockIntegrationConnection(): RedisConnectionInterface
{
    return IntegrationRedis::connectionFactory()->connection(IntegrationRedis::CONNECTION);
}

function redisLockIntegrationCleanUp(
    string $prefix,
): void {
    $client = redisLockIntegrationConnection()->client();
    $keys = $client->keys($prefix . '*');

    if ($keys !== []) {
        $client->del($keys);
    }
}

LockStoreContract::register(
    'RedisLockStore',
    fn (): LockStoreHarness => new class () implements LockStoreHarness
    {
        private string $prefix;

        private RedisLockStore $store;

        public function __construct()
        {
            $this->prefix = 'marko:test:' . bin2hex(random_bytes(6)) . ':';
            $this->store = redisLockIntegrationStore($this->prefix);
        }

        public function store(): LockStoreInterface
        {
            return $this->store;
        }

        public function factory(): LockFactoryInterface
        {
            return new LockFactory($this->store, new SystemClock(), new UsleepSleeper());
        }

        public function expire(
            string $name,
        ): void {
            redisLockIntegrationConnection()->client()->pexpire($this->prefix . 'lock:' . $name, 1);
            usleep(5_000);
        }

        public function expectedScope(): LockScope
        {
            return LockScope::Distributed;
        }

        public function clockSeconds(): float
        {
            return microtime(true);
        }

        public function cleanUp(): void
        {
            redisLockIntegrationCleanUp($this->prefix);
        }
    },
    fn (): bool => IntegrationRedis::unavailable(),
    IntegrationRedis::skipReason(),
);

describe('RedisLockStore against a real Redis server', function (): void {
    beforeEach(function (): void {
        $this->prefix = 'marko:test:' . bin2hex(random_bytes(6)) . ':';
    });

    afterEach(function (): void {
        if (!IntegrationRedis::unavailable()) {
            redisLockIntegrationCleanUp($this->prefix);
        }
    });

    it('stores the lock under the cache prefix plus lock: with a millisecond expiry', function (): void {
        redisLockIntegrationStore($this->prefix)->acquire('video:42', 'owner-a', 30);
        $client = redisLockIntegrationConnection()->client();

        expect($client->get($this->prefix . 'lock:video:42'))->toBe('owner-a')
            ->and($client->get($this->prefix . 'video:42'))->toBeNull()
            ->and($client->pttl($this->prefix . 'lock:video:42'))->toBeGreaterThan(29_000)->toBeLessThanOrEqual(
                30_000,
            );
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());

    it('refuses the lock to a second client while the first client holds it', function (): void {
        $first = redisLockIntegrationStore($this->prefix);
        $second = redisLockIntegrationStore($this->prefix);

        $results = [$first->acquire('race', 'client-a', 60), $second->acquire('race', 'client-b', 60)];

        expect($results)->toBe([true, false])
            ->and($second->currentOwner('race'))->toBe('client-a');
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());

    it('lets exactly one of several concurrent processes acquire the same lock', function (): void {
        $autoload = dirname(new ReflectionClass(ClassLoader::class)->getFileName(), 2) . '/autoload.php';
        $worker = dirname(__DIR__) . '/Fixtures/redis-lock-worker.php';
        $startAt = (string) (microtime(true) + 0.5);
        $processes = [];

        foreach (range(1, 6) as $i) {
            $process = proc_open(
                [PHP_BINARY, $worker, $autoload, $this->prefix, 'race', "worker-$i", $startAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
            );

            expect($process)->toBeResource();
            $processes[$i] = [$process, $pipes];
        }

        $results = [];

        foreach ($processes as $i => [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            expect(proc_close($process))->toBe(0, "worker $i failed: $errors")
                ->and($output)->toBeIn(['0', '1']);

            $results[$i] = $output;
        }

        $winners = array_keys($results, '1', true);

        expect($winners)->toHaveCount(1)
            ->and(redisLockIntegrationStore($this->prefix)->currentOwner('race'))->toBe('worker-' . $winners[0]);
    })->skip(fn (): bool => IntegrationRedis::unavailable(), IntegrationRedis::skipReason());
});
