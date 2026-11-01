<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Marko\Core\Command\Input;
use Marko\Scheduler\Exceptions\SchedulerException;

/**
 * A console command a scheduled task runs: its name and argv-style arguments.
 */
readonly class ScheduledCommand
{
    /** @var list<string> */
    public array $arguments;

    /**
     * @param array<mixed> $arguments Argv-style tokens, e.g. ['--days=30', 'users']
     *
     * @throws SchedulerException When an argument is not a string
     */
    public function __construct(
        public string $name,
        array $arguments = [],
    ) {
        foreach ($arguments as $argument) {
            if (!is_string($argument)) {
                throw SchedulerException::invalidCommandArgument($name, get_debug_type($argument));
            }
        }

        $this->arguments = array_values($arguments);
    }

    /**
     * The command name followed by its arguments, e.g. 'app:prune --days=30'.
     */
    public function description(): string
    {
        return implode(' ', [$this->name, ...$this->arguments]);
    }

    /**
     * The Input the command receives, as if its name and arguments had been typed after `marko`.
     */
    public function input(): Input
    {
        return new Input(['marko', $this->name, ...$this->arguments]);
    }
}
