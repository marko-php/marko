<?php

declare(strict_types=1);

namespace Marko\Queue\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\WorkerInterface;

/** @noinspection PhpUnused */
#[Command(name: 'queue:work', description: 'Process jobs from the queue')]
class WorkCommand implements CommandInterface
{
    private const int DEFAULT_SLEEP = 3;

    public function __construct(
        private readonly WorkerInterface $worker,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $args = $input->getArguments();
        $once = in_array('--once', $args, true);
        $queue = $this->getOptionValue($args, '--queue');
        $sleep = (int) ($this->getOptionValue($args, '--sleep') ?? self::DEFAULT_SLEEP);

        $output->writeLine('Processing jobs from queue...');

        $this->worker->work(queue: $queue, once: $once, sleep: $sleep);

        return 0;
    }

    /**
     * Get the value of an option (e.g., --queue=emails returns 'emails').
     *
     * @param array<string> $args
     */
    private function getOptionValue(
        array $args,
        string $option,
    ): ?string {
        $prefix = $option . '=';

        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return null;
    }
}
