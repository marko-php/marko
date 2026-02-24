<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use Closure;
use DateTimeInterface;

class Schedule
{
    /** @var array<ScheduledTask> */
    private array $tasks = [];

    public function call(
        Closure $callback,
    ): ScheduledTask {
        $task = new ScheduledTask($callback);
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
