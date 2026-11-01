<?php

declare(strict_types=1);

namespace Marko\Cache\File\Driver;

use Marko\Cache\CacheItem;
use Marko\Cache\CacheOperations;
use Marko\Cache\Config\CacheConfig;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Cache\Exceptions\TamperedCacheValueException;
use Marko\Cache\File\Exceptions\FileCacheException;
use Marko\Cache\File\Storage\FileEntryStorage;
use Marko\Cache\Signer\CacheValueSigner;
use Psr\Clock\ClockInterface;

readonly class FileCacheDriver implements CacheInterface
{
    private FileEntryStorage $storage;

    public function __construct(
        private CacheConfig $config,
        private ClockInterface $clock,
        CacheValueSigner $cacheValueSigner,
        private CacheOperations $operations,
    ) {
        $this->storage = new FileEntryStorage($config, $clock, $cacheValueSigner);
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->validateKey($key);

        $data = $this->storage->read($key);

        if ($data === null) {
            return $default;
        }

        if ($this->storage->isExpired($data)) {
            $this->delete($key);

            return $default;
        }

        return $data['value'];
    }

    /**
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
     */
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $this->storage->write($key, $this->storage->newEntry($value, $ttl ?? $this->config->defaultTtl()));

        return true;
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function has(
        string $key,
    ): bool {
        $this->validateKey($key);

        $data = $this->storage->read($key);

        if ($data === null) {
            return false;
        }

        if ($this->storage->isExpired($data)) {
            $this->delete($key);

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

        return $this->storage->delete($key);
    }

    public function clear(): bool
    {
        if (!is_dir($this->config->path())) {
            return true;
        }

        $cacheFiles = glob($this->config->path() . '/*.cache');

        if ($cacheFiles === false) {
            return false;
        }

        $tmpFiles = glob($this->config->path() . '/*.tmp.*');

        if ($tmpFiles === false) {
            return false;
        }

        $success = true;

        foreach (array_merge($cacheFiles, $tmpFiles) as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException|TamperedCacheValueException
     */
    public function getItem(
        string $key,
    ): CacheItemInterface {
        $this->validateKey($key);

        $data = $this->storage->read($key);

        if ($data === null) {
            return CacheItem::miss($key);
        }

        if ($this->storage->isExpired($data)) {
            $this->delete($key);

            return CacheItem::miss($key);
        }

        $expiresAt = $data['expires_at'] !== null
            ? $this->clock->now()->setTimestamp($data['expires_at'])
            : null;

        return CacheItem::hit($key, $data['value'], $expiresAt);
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
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
     */
    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        $success = true;

        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException
     */
    public function deleteMultiple(
        array $keys,
    ): bool {
        $success = true;

        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
     */
    public function increment(
        string $key,
        int $ttl,
    ): int {
        $this->validateKey($key);

        // Never fail open: a counter that silently restarts at 1 on every call
        // would let a rate limiter allow every request. mutateLocked() throws
        // when the entry cannot be opened, locked or written.
        return $this->storage->mutateLocked($key, function (?array $data) use ($ttl): array {
            if ($data === null) {
                return [$this->storage->newEntry(1, $ttl), 1];
            }

            $data['value'] = (int) $data['value'] + 1;

            return [$data, $data['value']];
        });
    }

    /**
     * Set-if-absent under the same exclusive file lock increment() uses, so of
     * any number of concurrent processes exactly one stores its value.
     *
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
     */
    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->validateKey($key);

        $ttl ??= $this->config->defaultTtl();

        return $this->storage->mutateLocked(
            $key,
            fn (?array $data): array => $data === null
                ? [$this->storage->newEntry($value, $ttl), true]
                : [null, false],
        );
    }

    /**
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
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
     * @throws InvalidKeyException|FileCacheException|TamperedCacheValueException
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
}
