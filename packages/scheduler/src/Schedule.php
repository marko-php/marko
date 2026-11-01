<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Closure;
use DateTimeInterface;
use Marko\Core\Command\CommandRegistry;
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
     * @throws SchedulerException When the command is not registered, or an argument is not a string
     */
    public function command(
        string $name,
        array $arguments = [],
    ): ScheduledTask {
        if (!$this->commandRegistry->has($name)) {
            throw SchedulerException::unknownCommand($name);
        }

        $task = new ScheduledTask(new ScheduledCommand($name, $arguments));
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
