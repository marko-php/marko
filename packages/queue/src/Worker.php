<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Exceptions\SerializationException;
use Throwable;

class Worker implements WorkerInterface
{
    private bool $running = false;

    public function __construct(
        private readonly QueueInterface $queue,
        private readonly FailedJobRepositoryInterface $failedJobRepository,
        private readonly QueueConfig $config,
        private readonly JobEnvelope $jobEnvelope,
        private readonly ContainerInterface $container,
    ) {}

    /**
     * @throws SerializationException
     */
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
                if ($job instanceof AsyncObserverJob) {
                    $job->setContainer($this->container);
                    $job->setJobEnvelope($this->jobEnvelope);
                }

                $job->incrementAttempts();
                $job->handle();
                $this->queue->delete($job->id);
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

    /**
     * @throws SerializationException
     */
    private function handleFailedJob(
        JobInterface $job,
        Throwable $e,
        ?string $queue,
    ): void {
        if ($job->attempts < $job->maxAttempts) {
            $delay = (int) pow(2, $job->attempts) * 10;
            $this->queue->release($job->id, $delay);
        } else {
            $this->failedJobRepository->store(new FailedJob(
                id: $job->id,
                queue: $queue ?? $this->config->queue(),
                payload: $this->jobEnvelope->wrap($job->serialize()),
                exception: $e->getMessage() . "\n" . $e->getTraceAsString(),
                failedAt: new DateTimeImmutable(),
            ));
            $this->queue->delete($job->id);
        }
    }
}
