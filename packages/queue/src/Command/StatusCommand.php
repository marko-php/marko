<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;

#[Command(name: 'queue:status', description: 'Show queue statistics')]
class StatusCommand implements CommandInterface
{
    public function __construct(
        private readonly QueueConfig $config,
        private readonly QueueInterface $queue,
        private readonly FailedJobRepositoryInterface $failedJobRepository,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $output->writeLine("Queue Driver: {$this->config->driver()}");
        $output->writeLine("Queue Name: {$this->config->queue()}");
        $output->writeLine("Pending Jobs: {$this->queue->size()}");
        $output->writeLine("Failed Jobs: {$this->failedJobRepository->count()}");

        return 0;
    }
}
