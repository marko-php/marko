<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Driver;

use Marko\Cache\CacheItem;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Cache\Signer\CacheValueSigner;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Redis\RedisConnectionInterface;
use Psr\Clock\ClockInterface;

readonly class RedisCacheDriver implements CacheInterface
{
    /**
     * Increment a counter and apply its TTL in one atomic step.
     *
     * The TTL is set when the counter is created, and also whenever the key has
     * no TTL at all (-1), so a counter left without an expiry can never limit a
     * client forever. An existing TTL is never reset, keeping the window fixed.
     */
    private const string INCREMENT_SCRIPT = <<<'LUA'
        local value = redis.call('INCR', KEYS[1])
        local ttl = tonumber(ARGV[1])
        if ttl > 0 and (value == 1 or redis.call('TTL', KEYS[1]) == -1) then
            redis.call('EXPIRE', KEYS[1], ttl)
        end
        return value
        LUA;

    private const string INTEGER_PATTERN = '/\A-?\d+\z/';

    /**
     * How many keys clear() asks SCAN to examine per round trip.
     */
    private const int SCAN_COUNT = 1000;

    private RedisConnectionInterface $connection;

    private string $prefix;

    /**
     * Resolves the marko/redis connection named by cache-redis.connection.
     *
     * @throws RedisConnectionException when that connection is not configured
     */
    public function __construct(
        RedisConnectionFactoryInterface $redisConnectionFactory,
        RedisCacheConfig $redisCacheConfig,
        private CacheConfig $config,
        private CacheValueSigner $cacheValueSigner,
        private ClockInterface $clock,
        private CacheOperations $operations,
    ) {
        $this->connection = $redisConnectionFactory->connection($redisCacheConfig->connection());
        $this->prefix = $redisCacheConfig->prefix();
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->validateKey($key);

        $prefixedKey = $this->prefixKey($key);
        $data = $this->connection->client()->get($prefixedKey);

        if ($data === null) {
            return $default;
        }

        return $this->decode($data, $prefixedKey);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $ttl ??= $this->config->defaultTtl();
        $prefixedKey = $this->prefixKey($key);
        $envelope = $this->cacheValueSigner->wrap(serialize($value), $prefixedKey);

        if ($ttl > 0) {
            $this->connection->client()->setex($prefixedKey, $ttl, $envelope);
        } else {
            $this->connection->client()->set($prefixedKey, $envelope);
        }

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
    public function has(
        string $key,
    ): bool {
        $this->validateKey($key);

        return $this->connection->client()->exists($this->prefixKey($key)) > 0;
    }

    /**
     * @throws InvalidKeyException
     */
    public function delete(
        string $key,
    ): bool {
        $this->validateKey($key);

        $this->connection->client()->del($this->prefixKey($key));

        return true;
    }

    /**
     * Delete every key under the configured prefix.
     *
     * Walks the keyspace with SCAN in batches instead of KEYS, which blocks the
     * Redis server for the whole scan on a large database. Glob characters in the
     * prefix are escaped so the MATCH pattern matches the prefix literally.
     */
    public function clear(): bool
    {
        $client = $this->connection->client();
        $pattern = $this->escapeGlob($this->prefix) . '*';
        $cursor = '0';

        do {
            [$cursor, $keys] = $client->scan($cursor, ['MATCH' => $pattern, 'COUNT' => self::SCAN_COUNT]);

            if ($keys !== []) {
                $client->del($keys);
            }
        } while ((string) $cursor !== '0');

        return true;
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function getItem(
        string $key,
    ): CacheItemInterface {
        $this->validateKey($key);

        $prefixedKey = $this->prefixKey($key);
        $client = $this->connection->client();
        $data = $client->get($prefixedKey);

        if ($data === null) {
            return CacheItem::miss($key);
        }

        // Redis enforces the expiry itself; the clock only anchors the reported time.
        $ttl = $client->ttl($prefixedKey);
        $now = $this->clock->now();
        $expiresAt = $ttl > 0
            ? $now->setTimestamp($now->getTimestamp() + $ttl)
            : null;

        return CacheItem::hit($key, $this->decode($data, $prefixedKey), $expiresAt);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        $prefixedKeys = array_map(fn ($k) => $this->prefixKey($k), $keys);
        $raw = $this->connection->client()->mget(...$prefixedKeys);

        $result = [];

        foreach ($keys as $i => $key) {
            $value = $raw[$i] ?? null;

            if ($value === null) {
                $result[$key] = $default;
            } else {
                $result[$key] = $this->decode($value, $prefixedKeys[$i]);
            }
        }

        return $result;
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        foreach ($values as $key => $value) {
            $this->validateKey($key);
        }

        $ttl ??= $this->config->defaultTtl();
        $client = $this->connection->client();

        $client->pipeline(function ($pipe) use ($values, $ttl): void {
            foreach ($values as $key => $value) {
                $prefixedKey = $this->prefixKey($key);
                $envelope = $this->cacheValueSigner->wrap(serialize($value), $prefixedKey);

                if ($ttl > 0) {
                    $pipe->setex($prefixedKey, $ttl, $envelope);
                } else {
                    $pipe->set($prefixedKey, $envelope);
                }
            }
        });

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
    public function deleteMultiple(
        array $keys,
    ): bool {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        $prefixedKeys = array_map(fn ($k) => $this->prefixKey($k), $keys);

        $this->connection->client()->del(...$prefixedKeys);

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
    public function increment(
        string $key,
        int $ttl,
    ): int {
        $this->validateKey($key);

        return (int) $this->connection->client()->eval(
            self::INCREMENT_SCRIPT,
            1,
            $this->prefixKey($key),
            $ttl,
        );
    }

    /**
     * Set-if-absent in one atomic SET ... NX, storing the same signed envelope
     * set() writes. A TTL of 0 stores the key with no expiry.
     *
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $ttl ??= $this->config->defaultTtl();
        $prefixedKey = $this->prefixKey($key);
        $envelope = $this->cacheValueSigner->wrap(serialize($value), $prefixedKey);
        $client = $this->connection->client();

        // Redis replies OK when the key was set and nil when NX found it present.
        $reply = $ttl > 0
            ? $client->set($prefixedKey, $envelope, 'EX', $ttl, 'NX')
            : $client->set($prefixedKey, $envelope, 'NX');

        return $reply !== null;
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function remember(
        string $key,
        ?int $ttl,
        callable $callback,
    ): mixed {
        return $this->operations->remember($this, $key, $ttl, $callback);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function pull(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->operations->pull($this, $key, $default);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function forever(
        string $key,
        mixed $value,
    ): bool {
        return $this->operations->forever($this, $key, $value);
    }

    /**
     * Decode a raw Redis value.
     *
     * Counters written by increment() are bare integers (Redis INCR cannot write
     * an HMAC envelope), so they are returned as ints. Everything else must be a
     * signed envelope; it is verified before unserialize() ever sees it. A signed
     * envelope always starts with 64 hex characters followed by '.', so it can
     * never be mistaken for an integer.
     *
     * The MAC is bound to the prefixed key the value was read from, so a valid
     * envelope copied from one key to another does not verify.
     *
     * @throws TamperedCacheValueException
     */
    private function decode(
        string $data,
        string $prefixedKey,
    ): mixed {
        if (preg_match(self::INTEGER_PATTERN, $data) === 1) {
            return (int) $data;
        }

        return unserialize($this->cacheValueSigner->verifyAndUnwrap($data, $prefixedKey));
    }

    /**
     * @throws InvalidKeyException
     */
    private function validateKey(
        string $key,
    ): void {
        if ($key === '') {
            throw InvalidKeyException::emptyKey();
        }

        if (!InvalidKeyException::isValidKey($key)) {
            throw InvalidKeyException::forKey($key);
        }
    }

    private function prefixKey(
        string $key,
    ): string {
        return $this->prefix . $key;
    }

    /**
     * Escape the Redis glob metacharacters (* ? [ ] \) so a pattern matches literally.
     */
    private function escapeGlob(
        string $value,
    ): string {
        return addcslashes($value, '*?[]\\');
    }
}
