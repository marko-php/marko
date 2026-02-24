<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Driver;

use DateTimeImmutable;
use Marko\Cache\CacheItem;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Redis\RedisConnection;

class RedisCacheDriver implements CacheInterface
{
    public function __construct(
        private readonly RedisConnection $connection,
        private readonly CacheConfig $config,
    ) {}

    /**
     * @throws InvalidKeyException
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

        return unserialize($data);
    }

    /**
     * @throws InvalidKeyException
     */
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $ttl ??= $this->config->defaultTtl();
        $prefixedKey = $this->prefixKey($key);
        $serialized = serialize($value);

        if ($ttl > 0) {
            $this->connection->client()->setex($prefixedKey, $ttl, $serialized);
        } else {
            $this->connection->client()->set($prefixedKey, $serialized);
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
     * @throws InvalidKeyException
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

        return CacheItem::hit($key, unserialize($data), $expiresAt);
    }

    /**
     * @throws InvalidKeyException
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
     * @throws InvalidKeyException
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
