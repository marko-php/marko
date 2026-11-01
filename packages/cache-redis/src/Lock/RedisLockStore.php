<?php

declare(strict_types=1);

namespace Marko\Cache\Redis\Lock;

use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Lock\LockScope;
use Marko\Cache\Redis\Config\RedisCacheConfig;
use Marko\Redis\Exceptions\RedisConnectionException;
use Marko\Redis\RedisConnectionFactoryInterface;
use Marko\Redis\RedisConnectionInterface;

/**
 * Lock store on a Redis server: excludes processes on every host that shares
 * it. Acquire is SET NX PX; release is a Lua compare-and-delete, so an owner
 * can never free a lock another owner claimed after its own expired.
 *
 * Lock keys are cache-redis.prefix plus 'lock:', on the marko/redis
 * connection named by cache-redis.connection.
 */
readonly class RedisLockStore implements LockStoreInterface
{
    private const string RELEASE_SCRIPT = <<<'LUA'
        if redis.call('GET', KEYS[1]) == ARGV[1] then
            return redis.call('DEL', KEYS[1])
        end
        return 0
        LUA;

    private RedisConnectionInterface $connection;

    private string $prefix;

    /**
     * @throws RedisConnectionException when the configured connection does not exist
     */
    public function __construct(
        RedisConnectionFactoryInterface $redisConnectionFactory,
        RedisCacheConfig $redisCacheConfig,
    ) {
        $this->connection = $redisConnectionFactory->connection($redisCacheConfig->connection());
        $this->prefix = $redisCacheConfig->prefix() . 'lock:';
    }

    public function acquire(
        string $name,
        string $owner,
        int $seconds,
    ): bool {
        return $this->connection->client()->set($this->key($name), $owner, 'PX', $seconds * 1_000, 'NX') !== null;
    }

    public function release(
        string $name,
        string $owner,
    ): bool {
        return (int) $this->connection->client()->eval(self::RELEASE_SCRIPT, 1, $this->key($name), $owner) === 1;
    }

    public function forceRelease(
        string $name,
    ): void {
        $this->connection->client()->del([$this->key($name)]);
    }

    public function currentOwner(
        string $name,
    ): ?string {
        return $this->connection->client()->get($this->key($name));
    }

    public function scope(): LockScope
    {
        return LockScope::Distributed;
    }

    private function key(
        string $name,
    ): string {
        return $this->prefix . $name;
    }
}
