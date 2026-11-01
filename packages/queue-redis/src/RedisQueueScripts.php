<?php

declare(strict_types=1);

namespace Marko\Queue\Redis;

/**
 * The Lua scripts RedisQueue runs with EVAL. Each one runs atomically on the server, so no other client sees a
 * job half-moved between the ready list, the delayed and reserved sorted sets and its job hash.
 *
 * Keys per queue: {prefix}queues:{name} (ready list of job ids), {prefix}queues:{name}:delayed (sorted set
 * scored by the time the job becomes available), {prefix}queues:{name}:reserved (sorted set scored by the
 * time the reservation expires) and {prefix}queues:{name}:notify (one token per ready job, which a blocking
 * pop waits on). Each job is a hash at {prefix}jobs:{id} with its queue, signed payload and attempt count.
 */
class RedisQueueScripts
{
    /**
     * Store a new job and queue it as ready, or as delayed when ARGV[6] is '1'.
     *
     * KEYS: job hash, ready, delayed, notify.
     * ARGV: queue name, payload, attempts, job id, available-at timestamp, delayed flag.
     */
    public const string PUSH = <<<'LUA'
        redis.call('hset', KEYS[1], 'queue', ARGV[1], 'payload', ARGV[2], 'attempts', ARGV[3])
        if ARGV[6] == '1' then
            redis.call('zadd', KEYS[3], ARGV[5], ARGV[4])
        else
            redis.call('rpush', KEYS[2], ARGV[4])
            redis.call('rpush', KEYS[4], 1)
        end
        return 1
        LUA;

    /**
     * Move due delayed jobs and expired reservations to ready, then reserve the first ready job: add it to the
     * reserved set until ARGV[2] and count the attempt. Returns {id, payload, reservations before this one},
     * or false when no job is ready. An id whose job hash is gone is dropped.
     *
     * KEYS: ready, delayed, reserved, notify.
     * ARGV: now timestamp, reservation expiry timestamp, job hash key prefix.
     */
    public const string POP = <<<'LUA'
        local function migrate(from)
            local ids = redis.call('zrangebyscore', from, '-inf', ARGV[1])
            for _, id in ipairs(ids) do
                redis.call('zrem', from, id)
                redis.call('rpush', KEYS[1], id)
                redis.call('rpush', KEYS[4], 1)
            end
        end
        migrate(KEYS[2])
        migrate(KEYS[3])
        while true do
            local id = redis.call('lpop', KEYS[1])
            if not id then
                redis.call('del', KEYS[4])
                return false
            end
            redis.call('lpop', KEYS[4])
            local jobKey = ARGV[3] .. id
            local payload = redis.call('hget', jobKey, 'payload')
            if payload then
                redis.call('zadd', KEYS[3], ARGV[2], id)
                local attempts = redis.call('hincrby', jobKey, 'attempts', 1)
                return {id, payload, attempts - 1}
            end
        end
        LUA;

    /**
     * Take a reserved job off the reserved set, store its new payload and attempts, and queue it as ready, or
     * as delayed when ARGV[5] is '1'. Returns 0 when the job is not reserved.
     *
     * KEYS: job hash, ready, delayed, notify, reserved.
     * ARGV: payload, attempts, job id, available-at timestamp, delayed flag.
     */
    public const string RELEASE = <<<'LUA'
        if redis.call('zrem', KEYS[5], ARGV[3]) == 0 then
            return 0
        end
        redis.call('hset', KEYS[1], 'payload', ARGV[1], 'attempts', ARGV[2])
        if ARGV[5] == '1' then
            redis.call('zadd', KEYS[3], ARGV[4], ARGV[3])
        else
            redis.call('rpush', KEYS[2], ARGV[3])
            redis.call('rpush', KEYS[4], 1)
        end
        return 1
        LUA;

    /**
     * Remove a job from wherever it is queued and delete its hash. Returns 1 when the hash existed.
     *
     * KEYS: job hash, ready, delayed, reserved.
     * ARGV: job id.
     */
    public const string DELETE = <<<'LUA'
        redis.call('lrem', KEYS[2], 0, ARGV[1])
        redis.call('zrem', KEYS[3], ARGV[1])
        redis.call('zrem', KEYS[4], ARGV[1])
        return redis.call('del', KEYS[1])
        LUA;

    /**
     * Delete every ready, delayed and reserved job of a queue with its hash. Returns how many jobs it removed.
     *
     * KEYS: ready, delayed, reserved, notify.
     * ARGV: job hash key prefix.
     */
    public const string CLEAR = <<<'LUA'
        local count = 0
        local function purge(ids)
            for _, id in ipairs(ids) do
                redis.call('del', ARGV[1] .. id)
                count = count + 1
            end
        end
        purge(redis.call('lrange', KEYS[1], 0, -1))
        purge(redis.call('zrange', KEYS[2], 0, -1))
        purge(redis.call('zrange', KEYS[3], 0, -1))
        redis.call('del', KEYS[1], KEYS[2], KEYS[3], KEYS[4])
        return count
        LUA;
}
