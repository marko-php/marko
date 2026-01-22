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
        return $this->config->getString('queue.driver', 'sync');
    }

    public function connection(): string
    {
        return $this->config->getString('queue.connection', 'default');
    }

    public function queue(): string
    {
        return $this->config->getString('queue.queue', 'default');
    }

    public function retryAfter(): int
    {
        return $this->config->getInt('queue.retry_after', 90);
    }

    public function maxAttempts(): int
    {
        return $this->config->getInt('queue.max_attempts', 3);
    }
}
