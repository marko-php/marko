<?php

declare(strict_types=1);

namespace Marko\Sse\Tests\Support;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\CacheItemInterface;
use Override;
use RuntimeException;

/**
 * Shared-store CacheInterface double that records increment()/delete() calls.
 * get() is deliberately unsupported: the connection limiter must never read values.
 */
class RecordingCache implements CacheInterface
{
    /**
     * @var array<string, int>
     */
    public array $values = [];

    /**
     * @var list<array{key: string, ttl: int}>
     */
    public array $increments = [];

    /**
     * @var list<string>
     */
    public array $deletes = [];

    #[Override]
    public function increment(
        string $key,
        int $ttl,
    ): int {
        $this->increments[] = ['key' => $key, 'ttl' => $ttl];
        $this->values[$key] = ($this->values[$key] ?? 0) + 1;

        return $this->values[$key];
    }

    #[Override]
    public function delete(
        string $key,
    ): bool {
        $this->deletes[] = $key;
        unset($this->values[$key]);

        return true;
    }

    #[Override]
    public function get(
        string $key,
        mixed $default = null,
    ): never {
        throw new RuntimeException('get() must not be used by the SSE connection limiter');
    }

    #[Override]
    public function set(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): never {
        throw new RuntimeException('set() is not supported by RecordingCache');
    }

    #[Override]
    public function has(
        string $key,
    ): bool {
        return isset($this->values[$key]);
    }

    #[Override]
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    #[Override]
    public function getItem(
        string $key,
    ): CacheItemInterface {
        throw new RuntimeException('getItem() is not supported by RecordingCache');
    }

    #[Override]
    public function getMultiple(
        array $keys,
        mixed $default = null,
    ): iterable {
        throw new RuntimeException('getMultiple() is not supported by RecordingCache');
    }

    #[Override]
    public function setMultiple(
        array $values,
        ?int $ttl = null,
    ): bool {
        throw new RuntimeException('setMultiple() is not supported by RecordingCache');
    }

    #[Override]
    public function deleteMultiple(
        array $keys,
    ): bool {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function add(
        string $key,
        mixed $value,
        ?int $ttl = null,
    ): never {
        throw new RuntimeException('add() is not supported by RecordingCache');
    }

    #[Override]
    public function remember(
        string $key,
        ?int $ttl,
        callable $callback,
    ): never {
        throw new RuntimeException('remember() is not supported by RecordingCache');
    }

    #[Override]
    public function pull(
        string $key,
        mixed $default = null,
    ): never {
        throw new RuntimeException('pull() is not supported by RecordingCache');
    }

    #[Override]
    public function forever(
        string $key,
        mixed $value,
    ): never {
        throw new RuntimeException('forever() is not supported by RecordingCache');
    }
}
