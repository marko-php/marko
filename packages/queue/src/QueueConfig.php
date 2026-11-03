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

    /**
     * Seconds a reservation lasts before a job that was neither deleted nor released is reclaimed.
     *
     * A queue that sets its own retry_after in queue.queues uses it; every other queue, and a null
     * queue, uses the global queue.retry_after.
     *
     * @throws QueueException When queue.queues or a queue's retry_after is invalid
     */
    public function retryAfter(
        ?string $queue = null,
    ): int {
        if ($queue !== null) {
            $retryAfters = $this->queueRetryAfters();

            if (array_key_exists($queue, $retryAfters)) {
                return $retryAfters[$queue];
            }
        }

        return $this->config->getInt('queue.retry_after');
    }

    /**
     * The config key the queue's retry_after comes from, for error messages that say what to change.
     *
     * @throws QueueException When queue.queues or a queue's retry_after is invalid
     */
    public function retryAfterKey(
        ?string $queue = null,
    ): string {
        if ($queue !== null && array_key_exists($queue, $this->queueRetryAfters())) {
            return "queue.queues.$queue.retry_after";
        }

        return 'queue.retry_after';
    }

    /**
     * The queues that set their own retry_after in queue.queues, keyed by queue name.
     *
     * Read as one array rather than by dot-notation key, so a queue name containing a dot works.
     *
     * @return array<string, int>
     * @throws QueueException When queue.queues is not a map of arrays, or a retry_after is not a positive int
     */
    public function queueRetryAfters(): array
    {
        if (!$this->config->has('queue.queues')) {
            return [];
        }

        $queues = $this->config->get('queue.queues');

        if (!is_array($queues)) {
            throw QueueException::invalidQueueSettings('queue.queues', get_debug_type($queues));
        }

        $retryAfters = [];

        foreach ($queues as $name => $settings) {
            $name = (string) $name;

            if (!is_array($settings)) {
                throw QueueException::invalidQueueSettings("queue.queues.$name", get_debug_type($settings));
            }

            if (!array_key_exists('retry_after', $settings)) {
                continue;
            }

            $retryAfter = $settings['retry_after'];

            if (!is_int($retryAfter) || $retryAfter < 1) {
                throw QueueException::invalidQueueRetryAfter($name, $retryAfter);
            }

            $retryAfters[$name] = $retryAfter;
        }

        return $retryAfters;
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

    /**
     * Where queue:restart stores the restart token: 'file' (FileRestartSignal) or 'cache' (CacheRestartSignal).
     */
    public function restartSignalDriver(): string
    {
        return $this->config->getString('queue.restart_signal_driver');
    }

    public function maxAttempts(): int
    {
        return $this->config->getInt('queue.max_attempts');
    }

    /**
     * The default retry backoff: an int (fixed seconds), a list of seconds per attempt,
     * or null for the built-in exponential curve (2^attempts * 10 seconds).
     *
     * An absent key also means null. Any other value must be a non-negative int or a
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
