<?php

declare(strict_types=1);

use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Clock\SystemClock;
use Marko\Core\Support\ErrorCapture;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\RateLimiter\RateLimiter;
use Marko\Redis\RedisConnectionFactory;
use Marko\Testing\Fake\FakeConfigRepository;
use Predis\Client;
use Psr\Clock\ClockInterface;

/*
 * Runs RateLimiter on marko/cache-redis against a real Redis server. Point it
 * at one with MARKO_TEST_REDIS_HOST / MARKO_TEST_REDIS_PORT (default
 * 127.0.0.1:6379); the tests skip with a clear reason when no server answers.
 * CI provides one.
 */

function rateLimiterRedisHost(): string
{
    return getenv('MARKO_TEST_REDIS_HOST') ?: '127.0.0.1';
}

function rateLimiterRedisPort(): int
{
    return (int) (getenv('MARKO_TEST_REDIS_PORT') ?: 6379);
}

function rateLimiterRedisSkipReason(): string
{
    return sprintf(
        'Redis is not reachable at %s:%d: %s. Start one (e.g. `docker run -p 6379:6379 redis:7-alpine`) or set MARKO_TEST_REDIS_HOST / MARKO_TEST_REDIS_PORT.',
        rateLimiterRedisHost(),
        rateLimiterRedisPort(),
        rateLimiterRedisUnreachableReason() ?? 'no reason given',
    );
}

/**
 * Pings Redis once and returns why it is unreachable, or null when it answers.
 * Predis suppresses its own connect warning with @, which PHPUnit still records,
 * so the ping runs inside ErrorCapture to keep the skip probe warning-free.
 */
function rateLimiterRedisUnreachableReason(): ?string
{
    static $probed = false;
    static $reason = null;

    if (!$probed) {
        $probed = true;

        try {
            ErrorCapture::run($warning, fn (): mixed => new Client([
                'host' => rateLimiterRedisHost(),
                'port' => rateLimiterRedisPort(),
                'timeout' => 0.5,
            ])->ping());
        } catch (Throwable $e) {
            $reason = $e->getMessage();
        }
    }

    return $reason;
}

function rateLimiterRedisUnavailable(): bool
{
    return rateLimiterRedisUnreachableReason() !== null;
}

/**
 * A marko/redis factory with one 'integration' connection on database 15.
 */
function createRateLimiterRedisConnectionFactory(): RedisConnectionFactory
{
    $settings = [
        'host' => rateLimiterRedisHost(),
        'port' => rateLimiterRedisPort(),
        'password' => null,
        'database' => 15,
        'scheme' => 'tcp',
    ];
    $config = ['redis.connections' => ['integration' => $settings]];

    foreach ($settings as $key => $value) {
        $config["redis.connections.integration.$key"] = $value;
    }

    return new RedisConnectionFactory(new FakeConfigRepository($config));
}

function createRateLimiterRedisCache(
    ClockInterface $clock,
): RedisCacheDriver {
    return new RedisCacheDriver(
        createRateLimiterRedisConnectionFactory(),
        new RedisCacheConfig(new FakeConfigRepository([
            'cache-redis.connection' => 'integration',
            'cache-redis.prefix' => 'marko:test:' . bin2hex(random_bytes(6)) . ':',
        ])),
        new CacheConfig(new FakeConfigRepository([
            'cache.path' => '/tmp/cache',
            'cache.default_ttl' => 3600,
            'cache.driver' => 'redis',
        ])),
        new CacheValueSigner(new EncryptionConfig(new FakeConfigRepository([
            'encryption.key' => 'integration-signing-key',
        ]))),
        $clock,
        new CacheOperations(),
    );
}

describe('RateLimiter on a real Redis server', function (): void {
    beforeEach(function (): void {
        if (rateLimiterRedisUnavailable()) {
            return;
        }

        $clock = new SystemClock();
        $this->cache = createRateLimiterRedisCache($clock);
        $this->limiter = new RateLimiter($this->cache, $clock);
    });

    afterEach(function (): void {
        if (isset($this->cache)) {
            $this->cache->clear();
        }
    });

    it('denies the third attempt with a positive retryAfter against redis', function (): void {
        $first = $this->limiter->attempt('203.0.113.50', 2, 60);
        $second = $this->limiter->attempt('203.0.113.50', 2, 60);
        $third = $this->limiter->attempt('203.0.113.50', 2, 60);

        expect($first->allowed())->toBeTrue()
            ->and($second->allowed())->toBeTrue()
            ->and($third->allowed())->toBeFalse()
            ->and($third->retryAfter())->toBeGreaterThan(0)
            ->toBeLessThanOrEqual(60);
    })->skip(fn (): bool => rateLimiterRedisUnavailable(), rateLimiterRedisSkipReason());

    it('reports tooManyAttempts from a redis counter', function (): void {
        $this->limiter->attempt('203.0.113.50', 2, 60);
        $this->limiter->attempt('203.0.113.50', 2, 60);

        expect($this->limiter->tooManyAttempts('203.0.113.50', 2))->toBeTrue()
            ->and($this->limiter->tooManyAttempts('203.0.113.51', 2))->toBeFalse();
    })->skip(fn (): bool => rateLimiterRedisUnavailable(), rateLimiterRedisSkipReason());

    it('limits an IPv6 client against redis', function (): void {
        $this->limiter->attempt('2001:db8::1', 1, 60);
        $denied = $this->limiter->attempt('2001:db8::1', 1, 60);

        expect($denied->allowed())->toBeFalse()
            ->and($denied->retryAfter())->toBeGreaterThan(0);
    })->skip(fn (): bool => rateLimiterRedisUnavailable(), rateLimiterRedisSkipReason());
});
