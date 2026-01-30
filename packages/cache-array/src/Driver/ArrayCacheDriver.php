<?php

declare(strict_types=1);

namespace Marko\Cache\Memory\Driver;

use DateTimeImmutable;
use Marko\Cache\CacheItem;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;

/**
 * In-memory array cache driver.
 *
 * Stores cache data in memory for the duration of the request.
 * Data does not persist across requests - use cache-file or
 * cache-redis for persistent caching.
 *
 * Ideal for:
 * - Development and testing
 * - Single-request caching (e.g., avoiding duplicate queries)
 * - Environments where file/redis are unavailable
 */
class ArrayCacheDriver implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: ?int, created_at: int}>
     */
    private array $storage = [];

    public function __construct(
        private readonly CacheConfig $config,
    ) {}

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->validateKey($key);

        if (!isset($this->storage[$key])) {
            return $default;
        }

        if ($this->isExpired($this->storage[$key])) {
            unset($this->storage[$key]);

            return $default;
        }

        return $this->storage[$key]['value'];
    }

    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $ttl ??= $this->config->defaultTtl();
        $expiresAt = $ttl > 0 ? time() + $ttl : null;

        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
            'created_at' => time(),
        ];

        return true;
    }

    public function has(
        string $key,
    ): bool {
        $this->validateKey($key);

        if (!isset($this->storage[$key])) {
            return false;
        }

        if ($this->isExpired($this->storage[$key])) {
            unset($this->storage[$key]);

            return false;
        }

        return true;
    }

    public function delete(
        string $key,
    ): bool {
        $this->validateKey($key);

        unset($this->storage[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];

        return true;
    }

    public function getItem(
        string $key,
    ): CacheItemInterface {
        $this->validateKey($key);

        if (!isset($this->storage[$key])) {
            return CacheItem::miss($key);
        }

        if ($this->isExpired($this->storage[$key])) {
            unset($this->storage[$key]);

            return CacheItem::miss($key);
        }

        $data = $this->storage[$key];
        $expiresAt = $data['expires_at'] !== null
            ? (new DateTimeImmutable())->setTimestamp($data['expires_at'])
            : null;

        return CacheItem::hit($key, $data['value'], $expiresAt);
    }

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

    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

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

    /**
     * @param array{value: mixed, expires_at: ?int, created_at: int} $data
     */
    private function isExpired(
        array $data,
    ): bool {
        if ($data['expires_at'] === null) {
            return false;
        }

        return time() > $data['expires_at'];
    }
}
