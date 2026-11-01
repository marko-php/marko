<?php

declare(strict_types=1);

namespace Marko\Cache\Memory\Driver;

use Marko\Cache\CacheItem;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Psr\Clock\ClockInterface;

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
        private readonly ClockInterface $clock,
        private readonly CacheOperations $operations,
    ) {}

    /**
     * @throws InvalidKeyException
     */
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
        $now = $this->clock->now()->getTimestamp();

        $this->storage[$key] = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? $now + $ttl : null,
            'created_at' => $now,
        ];

        return true;
    }

    /**
     * @throws InvalidKeyException
     */
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

    /**
     * @throws InvalidKeyException
     */
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

    /**
     * @throws InvalidKeyException
     */
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
            ? $this->clock->now()->setTimestamp($data['expires_at'])
            : null;

        return CacheItem::hit($key, $data['value'], $expiresAt);
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
    public function increment(
        string $key,
        int $ttl,
    ): int {
        $this->validateKey($key);

        if (!isset($this->storage[$key]) || $this->isExpired($this->storage[$key])) {
            $now = $this->clock->now()->getTimestamp();
            $this->storage[$key] = [
                'value' => 1,
                'expires_at' => $ttl > 0 ? $now + $ttl : null,
                'created_at' => $now,
            ];

            return 1;
        }

        $this->storage[$key]['value']++;

        return $this->storage[$key]['value'];
    }

    /**
     * Set-if-absent. The store lives in this process and nothing can suspend
     * between the check and the write, so no other caller can interleave.
     *
     * @throws InvalidKeyException
     */
    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    /**
     * @throws InvalidKeyException
     */
    public function remember(
        string $key,
        ?int $ttl,
        callable $callback,
    ): mixed {
        return $this->operations->remember($this, $key, $ttl, $callback);
    }

    /**
     * @throws InvalidKeyException
     */
    public function pull(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->operations->pull($this, $key, $default);
    }

    /**
     * @throws InvalidKeyException
     */
    public function forever(
        string $key,
        mixed $value,
    ): bool {
        return $this->operations->forever($this, $key, $value);
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

        return $this->clock->now()->getTimestamp() > $data['expires_at'];
    }
}
