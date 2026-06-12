<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Driver;

use DateTimeImmutable;
use Marko\Cache\CacheItem;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Redis\Exceptions\TamperedCacheValueException;
use Marko\Cache\Redis\RedisConnection;
use Marko\Cache\Redis\Signer\CacheValueSigner;

readonly class RedisCacheDriver implements CacheInterface
{
    public function __construct(
        private RedisConnection $connection,
        private CacheConfig $config,
        private CacheValueSigner $cacheValueSigner,
    ) {}

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->validateKey($key);

        $data = $this->connection->client()->get($this->prefixKey($key));

        if ($data === null) {
            return $default;
        }

        return unserialize($this->cacheValueSigner->verifyAndUnwrap($data));
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
        $envelope = $this->cacheValueSigner->wrap(serialize($value));

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

    public function clear(): bool
    {
        $client = $this->connection->client();
        $keys = $client->keys($this->connection->prefix . '*');

        if ($keys !== []) {
            $client->del($keys);
        }

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

        $ttl = $client->ttl($prefixedKey);
        $expiresAt = $ttl > 0
            ? (new DateTimeImmutable())->setTimestamp(time() + $ttl)
            : null;

        return CacheItem::hit($key, unserialize($this->cacheValueSigner->verifyAndUnwrap($data)), $expiresAt);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
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
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
    public function deleteMultiple(
        array $keys,
    ): bool {
        foreach ($keys as $key) {
            $this->delete($key);
        }

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

        $client = $this->connection->client();
        $prefixedKey = $this->prefixKey($key);

        $newValue = $client->incr($prefixedKey);

        if ($newValue === 1 && $ttl > 0) {
            $client->expire($prefixedKey, $ttl);
        }

        return $newValue;
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
        return $this->connection->prefix . $key;
    }
}
