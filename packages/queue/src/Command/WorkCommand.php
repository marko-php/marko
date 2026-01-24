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
    public function __construct(
        private readonly WorkerInterface $worker,
    ) {}

    private const int DEFAULT_SLEEP = 3;

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $once = $this->hasOption($input, '--once');
        $queue = $this->getOptionValue($input, '--queue');
        $sleep = $this->getOptionIntValue($input, '--sleep', self::DEFAULT_SLEEP);

        $output->writeLine('Processing jobs from queue...');

        $this->worker->work(queue: $queue, once: $once, sleep: $sleep);

        return 0;
    }

    /**
     * Check if an option flag exists in the input arguments.
     */
    private function hasOption(
        Input $input,
        string $option,
    ): bool {
        return in_array($option, $input->getArguments(), true);
    }

    /**
     * Get the value of an option (e.g., --queue=emails returns 'emails').
     */
    private function getOptionValue(
        Input $input,
        string $option,
    ): ?string {
        $prefix = $option . '=';

        foreach ($input->getArguments() as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }

        return null;
    }

    /**
     * Get the integer value of an option with a default.
     */
    private function getOptionIntValue(
        Input $input,
        string $option,
        int $default,
    ): int {
        $value = $this->getOptionValue($input, $option);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }
}
