<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

/** @noinspection PhpUnused */
#[Command(name: 'queue:retry', description: 'Retry failed jobs', flags: ['all'])]
readonly class RetryCommand implements CommandInterface
{
    public function __construct(
        private FailedJobRepositoryInterface $failedJobRepository,
        private QueueInterface $queue,
        private JobEnvelope $jobEnvelope,
    ) {}

    /**
     * @throws SerializationException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        if ($input->hasOption('all')) {
            return $this->retryAll($output);
        }

        $jobId = $input->getArgument(0);

        if ($jobId === null) {
            $output->writeLine('Please provide a job ID or use --all flag.');

            return 1;
        }

        return $this->retryJob($jobId, $output);
    }

    /**
     * @throws SerializationException
     */
    private function retryAll(
        Output $output,
    ): int {
        $failedJobs = $this->failedJobRepository->all();

        if ($failedJobs === []) {
            $output->writeLine('No failed jobs to retry.');

            return 0;
        }

        $count = 0;
        $skipped = 0;

        foreach ($failedJobs as $failedJob) {
            $job = $this->unserializeJob($failedJob);

            if (!$job instanceof JobInterface) {
                $output->writeLine($this->notRetryableMessage($failedJob->id, $job));
                $skipped++;

                continue;
            }

            $this->resetForRetry($job);
            $this->queue->push($job, $failedJob->queue);
            $this->failedJobRepository->delete($failedJob->id);
            $count++;
        }

        $output->writeLine("$count jobs pushed back to queue.");

        if ($skipped > 0) {
            $output->writeLine($skipped === 1 ? '1 job skipped.' : "$skipped jobs skipped.");

            return 1;
        }

        return 0;
    }

    /**
     * @throws SerializationException
     */
    private function retryJob(
        string $jobId,
        Output $output,
    ): int {
        $failedJob = $this->failedJobRepository->find($jobId);

        if ($failedJob === null) {
            $output->writeLine("Job $jobId not found.");

            return 1;
        }

        $job = $this->unserializeJob($failedJob);

        if (!$job instanceof JobInterface) {
            $output->writeLine($this->notRetryableMessage($jobId, $job));

            return 1;
        }

        $this->resetForRetry($job);

        // Push it back to the queue
        $this->queue->push($job, $failedJob->queue);

        // Delete from failed jobs
        $this->failedJobRepository->delete($jobId);

        $output->writeLine("Job $jobId pushed back to queue.");

        return 0;
    }

    /**
     * Give a failed job a fresh start: no attempts, no exceptions, no release request, and no retry
     * deadline, so a RetryUntilInterface job gets a new deadline when it is pushed again instead of
     * failing straight away on the one it already passed.
     */
    private function resetForRetry(
        JobInterface $job,
    ): void {
        $job->resetAttempts();
        $job->resetExceptions();
        $job->clearRetryDeadline();
        $job->clearRelease();
    }

    /**
     * Verify the payload's signature and unserialize it. The result is not always a job: the
     * worker stores a placeholder array for a job whose payload could not be serialized.
     *
     * @throws SerializationException
     */
    private function unserializeJob(
        FailedJob $failedJob,
    ): mixed {
        return unserialize($this->jobEnvelope->verifyAndUnwrap($failedJob->payload));
    }

    private function notRetryableMessage(
        string $jobId,
        mixed $payload,
    ): string {
        if (is_array($payload) && is_string($payload['class'] ?? null)
            && is_string($payload['serialization_error'] ?? null)) {
            return "Job $jobId cannot be retried: its payload could not be serialized when it failed "
                . "({$payload['class']}: {$payload['serialization_error']}). "
                . 'Remove closures, resources and live services from the job, then dispatch it again.';
        }

        return "Job $jobId cannot be retried: its payload is not a queue job ("
            . get_debug_type($payload) . ').';
    }
}
