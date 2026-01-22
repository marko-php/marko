<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;
use Throwable;

class Worker implements WorkerInterface
{
    private bool $running = false;

    public function __construct(
        private QueueInterface $queue,
        private FailedJobRepositoryInterface $failedRepository,
        private QueueConfig $config,
    ) {}

    public function work(
        ?string $queue = null,
        bool $once = false,
        int $sleep = 3,
    ): void {
        $this->running = true;

        while ($this->running) {
            $job = $this->queue->pop($queue);

            if ($job === null) {
                if ($once) {
                    return;
                }
                sleep($sleep);

                continue;
            }

            try {
                $job->incrementAttempts();
                $job->handle();
                $this->queue->delete($job->getId());
            } catch (Throwable $e) {
                $this->handleFailedJob($job, $e, $queue);
            }

            if ($once) {
                return;
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function handleFailedJob(
        JobInterface $job,
        Throwable $e,
        ?string $queue,
    ): void {
        if ($job->getAttempts() < $job->getMaxAttempts()) {
            $delay = (int) pow(2, $job->getAttempts()) * 10;
            $this->queue->release($job->getId(), $delay);
        } else {
            $this->failedRepository->store(new FailedJob(
                id: $job->getId(),
                queue: $queue ?? $this->config->queue(),
                payload: $job->serialize(),
                exception: $e->getMessage() . "\n" . $e->getTraceAsString(),
                failedAt: new DateTimeImmutable(),
            ));
            $this->queue->delete($job->getId());
        }
    }
}
