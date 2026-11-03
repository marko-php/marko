<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Closure;
use DateTimeInterface;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\Input;
use Marko\Scheduler\Exceptions\SchedulerException;

class Schedule
{
    /** @var array<ScheduledTask> */
    private array $tasks = [];

    public function __construct(
        private readonly CommandRegistry $commandRegistry,
    ) {}

    public function call(
        Closure $callback,
    ): ScheduledTask {
        $task = new ScheduledTask($callback);
        $this->tasks[] = $task;

        return $task;
    }

    /**
     * Schedule a registered console command, e.g. command('app:prune', ['--days=30']).
     *
     * @param array<mixed> $arguments Argv-style tokens, as you would type them after the command name
     * @throws SchedulerException When the command is not registered, an argument is not a string, or an option is
     *                            one the command does not declare
     */
    public function command(
        string $name,
        array $arguments = [],
    ): ScheduledTask {
        $definition = $this->commandRegistry->get($name);

        if ($definition === null) {
            throw SchedulerException::unknownCommand($name);
        }

        $command = new ScheduledCommand($name, $arguments);
        $undeclaredOptions = new Input(['marko', $name, ...$command->arguments], $definition->flags)
            ->undeclaredOptions($definition->acceptedOptions());

        if ($undeclaredOptions !== []) {
            throw SchedulerException::undeclaredCommandOptions(
                $name,
                $undeclaredOptions,
                $definition->acceptedOptionUsages(),
            );
        }

        $task = new ScheduledTask($command);
        $this->tasks[] = $task;

        return $task;
    }

    /** @return array<ScheduledTask> */
    public function tasks(): array
    {
        return $this->tasks;
    }

    /** @return array<ScheduledTask> */
    public function dueTasksAt(
        DateTimeInterface $time,
    ): array {
        return array_values(array_filter(
            $this->tasks,
            fn (ScheduledTask $task): bool => $task->isDue($time),
        ));
    }
}
