<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\Exceptions\QueueException;

readonly class QueueConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private BackoffValidator $backoffValidator = new BackoffValidator(),
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

    /**
     * Default seconds one job may run under queue:work before it is failed; 0 disables the timeout.
     */
    public function timeout(): int
    {
        return $this->config->getInt('queue.timeout');
    }

    /**
     * The file FileRestartSignal writes the restart token to; a relative path is resolved against the project root.
     */
    public function restartSignal(): string
    {
        return $this->config->getString('queue.restart_signal');
    }

    public function maxAttempts(): int
    {
        return $this->config->getInt('queue.max_attempts');
    }

    /**
     * The default retry backoff: an int (fixed seconds), a list of seconds per attempt,
     * or null for the built-in exponential curve (2^attempts * 10 seconds).
     *
     * An app config that sets `backoff` to null removes the key during config merging,
     * so an absent key also means null. Any other value must be a non-negative int or a
     * non-empty list of non-negative ints.
     *
     * @return int|list<int>|null
     * @throws QueueException
     */
    public function backoff(): array|int|null
    {
        if (!$this->config->has('queue.backoff')) {
            return null;
        }

        $backoff = $this->config->get('queue.backoff');

        if ($backoff === null) {
            return null;
        }

        return $this->backoffValidator->validate($backoff, 'config queue.backoff');
    }
}
