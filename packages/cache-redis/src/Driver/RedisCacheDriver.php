<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Driver;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use RuntimeException;

class RedisCacheDriver implements CacheInterface
{
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        return $default;
    }

    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): bool {
        return false;
    }

    public function has(
        string $key,
    ): bool {
        return false;
    }

    public function delete(
        string $key,
    ): bool {
        return false;
    }

    public function clear(): bool
    {
        return false;
    }

    public function getItem(
        string $key,
    ): CacheItemInterface {
        throw new RuntimeException('Not implemented');
    }

    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable {
        return [];
    }

    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        return false;
    }

    public function deleteMultiple(
        array $keys,
    ): bool {
        return false;
    }
}
