<?php

declare(strict_types=1);

namespace Marko\Queue\Redis;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\Redis\Exceptions\RedisQueueException;

/**
 * The queue-redis.* config. The reservation timeout is not here: the driver uses the queue's
 * retry_after (queue.queues.<name>.retry_after, else queue.retry_after).
 */
readonly class RedisQueueConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * The marko/redis connection name (a key of redis.connections).
     */
    public function connection(): string
    {
        return $this->config->getString('queue-redis.connection');
    }

    /**
     * Prepended to every key the driver writes.
     */
    public function prefix(): string
    {
        return $this->config->getString('queue-redis.prefix');
    }

    /**
     * Seconds a blocking pop waits for a job to arrive; 0 turns blocking pops off.
     *
     * @throws RedisQueueException When the value is negative
     */
    public function blockFor(): int
    {
        $blockFor = $this->config->getInt('queue-redis.block_for');

        if ($blockFor < 0) {
            throw RedisQueueException::invalidBlockFor($blockFor);
        }

        return $blockFor;
    }
}
