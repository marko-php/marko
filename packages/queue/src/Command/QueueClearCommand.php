<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\QueueInterface;

#[Command(name: 'queue:clear', description: 'Clear all jobs from queue')]
class QueueClearCommand implements CommandInterface
{
    public function __construct(
        private QueueInterface $queue,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $queueName = $input->getOption('queue');
        $count = $this->queue->clear($queueName);

        if ($queueName !== null) {
            $output->writeLine("Cleared $count jobs from '$queueName' queue.");
        } else {
            $output->writeLine("Cleared $count jobs from queue.");
        }

        return 0;
    }
}
