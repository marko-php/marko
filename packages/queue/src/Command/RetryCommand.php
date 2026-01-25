<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

/** @noinspection PhpUnused */
#[Command(name: 'queue:retry', description: 'Retry failed jobs')]
readonly class RetryCommand implements CommandInterface
{
    public function __construct(
        private FailedJobRepositoryInterface $failedJobRepository,
        private QueueInterface $queue,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        if ($this->hasAllFlag($input)) {
            return $this->retryAll($output);
        }

        $jobId = $input->getArgument(0);

        if ($jobId === null) {
            $output->writeLine('Please provide a job ID or use --all flag.');

            return 1;
        }

        return $this->retryJob($jobId, $output);
    }

    private function hasAllFlag(
        Input $input,
    ): bool {
        foreach ($input->getArguments() as $arg) {
            if ($arg === '--all') {
                return true;
            }
        }

        return false;
    }

    private function retryAll(
        Output $output,
    ): int {
        $failedJobs = $this->failedJobRepository->all();

        if ($failedJobs === []) {
            $output->writeLine('No failed jobs to retry.');

            return 0;
        }

        $count = 0;

        foreach ($failedJobs as $failedJob) {
            /** @var JobInterface $job */
            $job = unserialize($failedJob->payload);
            $this->queue->push($job, $failedJob->queue);
            $this->failedJobRepository->delete($failedJob->id);
            $count++;
        }

        $output->writeLine("$count jobs pushed back to queue.");

        return 0;
    }

    private function retryJob(
        string $jobId,
        Output $output,
    ): int {
        $failedJob = $this->failedJobRepository->find($jobId);

        if ($failedJob === null) {
            $output->writeLine("Job $jobId not found.");

            return 1;
        }

        // Unserialize the job from the payload
        /** @var JobInterface $job */
        $job = unserialize($failedJob->payload);

        // Push it back to the queue
        $this->queue->push($job, $failedJob->queue);

        // Delete from failed jobs
        $this->failedJobRepository->delete($jobId);

        $output->writeLine("Job $jobId pushed back to queue.");

        return 0;
    }
}
