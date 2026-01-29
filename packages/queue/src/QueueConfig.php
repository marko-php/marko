<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Config\ConfigRepositoryInterface;

readonly class QueueConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function driver(): string
    {
        return $this->config->getString('queue.driver');
    }

    public function connection(): string
    {
        return $this->config->getString('queue.connection');
    }

    public function queue(): string
    {
        return $this->config->getString('queue.queue');
    }

    public function retryAfter(): int
    {
        return $this->config->getInt('queue.retry_after');
    }

    public function maxAttempts(): int
    {
        return $this->config->getInt('queue.max_attempts');
    }
}
