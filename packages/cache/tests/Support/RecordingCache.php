<?php

declare(strict_types=1);

namespace Marko\Cache\Tests\Support;

use Marko\Cache\CacheItem;
use Marko\Cache\CacheOperations;
use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;

/**
 * In-memory CacheInterface double that records every set() and delete() call,
 * so CacheOperations tests can assert which primitive operations it performed.
 */
class RecordingCache implements CacheInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * @var list<array{key: string, value: mixed, ttl: ?int}>
     */
    public array $sets = [];

    /**
     * @var list<string>
     */
    public array $deletes = [];

    public function __construct(
        private readonly CacheOperations $operations = new CacheOperations(),
    ) {}

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        $this->sets[] = ['key' => $key, 'value' => $value, 'ttl' => $ttl];
        $this->values[$key] = $value;

        return true;
    }

    public function has(
        string $key,
    ): bool {
        return array_key_exists($key, $this->values);
    }

    public function delete(
        string $key,
    ): bool {
        $this->deletes[] = $key;
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function getItem(
        string $key,
    ): CacheItemInterface {
        return array_key_exists($key, $this->values)
            ? CacheItem::hit($key, $this->values[$key])
            : CacheItem::miss($key);
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

    public function increment(
        string $key,
        int $ttl,
    ): int {
        $this->values[$key] = (int) ($this->values[$key] ?? 0) + 1;

        return $this->values[$key];
    }

    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        return $this->set($key, $value, $ttl);
    }

    public function remember(
        string $key,
        ?int $ttl,
        callable $callback,
    ): mixed {
        return $this->operations->remember($this, $key, $ttl, $callback);
    }

    public function pull(
        string $key,
        mixed $default = null,
    ): mixed {
        return $this->operations->pull($this, $key, $default);
    }

    public function forever(
        string $key,
        mixed $value,
    ): bool {
        return $this->operations->forever($this, $key, $value);
    }
}
