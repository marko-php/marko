<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\FailedJobRepositoryInterface;

/** @noinspection PhpUnused */
#[Command(name: 'queue:failed', description: 'List all failed jobs')]
readonly class FailedCommand implements CommandInterface
{
    public function __construct(
        private FailedJobRepositoryInterface $repository,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $failedJobs = $this->repository->all();

        if (count($failedJobs) === 0) {
            $output->writeLine('No failed jobs.');

            return 0;
        }

        // Display header
        $output->writeLine(
            '+--------------------------------------+----------+----------------------+---------------------+',
        );
        $output->writeLine(
            '| ID                                   | Queue    | Job                  | Failed At           |',
        );
        $output->writeLine(
            '+--------------------------------------+----------+----------------------+---------------------+',
        );

        // Display failed jobs
        foreach ($failedJobs as $job) {
            $jobClass = $this->extractJobClass($job->payload);
            $failedAt = $job->failedAt->format('Y-m-d H:i:s');

            $output->writeLine(sprintf(
                '| %-36s | %-8s | %-20s | %-19s |',
                $job->id,
                $this->truncate($job->queue, 8),
                $this->truncate($jobClass, 20),
                $failedAt,
            ));
        }

        $output->writeLine(
            '+--------------------------------------+----------+----------------------+---------------------+',
        );

        // Display total count
        $count = count($failedJobs);
        $jobWord = $count === 1 ? 'job' : 'jobs';
        $output->writeLine("Total: $count failed $jobWord");

        return 0;
    }

    private function extractJobClass(
        string $payload,
    ): string {
        $data = @unserialize($payload);

        if (is_array($data) && isset($data['class'])) {
            return $data['class'];
        }

        return 'Unknown';
    }

    private function truncate(
        string $value,
        int $maxLength,
    ): string {
        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength - 3) . '...';
    }
}
