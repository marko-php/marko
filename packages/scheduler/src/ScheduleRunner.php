<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use DateTimeInterface;
use Marko\Core\Command\Output;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Throwable;

/**
 * Runs the tasks that are due at a given time. Shared by schedule:run and schedule:work.
 *
 * A failing task is reported and counted but does not stop the remaining due tasks.
 * A due task whose when()/skip() conditions do not allow the run is skipped before its
 * overlap mutex is acquired; a condition that throws counts as a failure of that task.
 * Tasks marked withoutOverlapping() are skipped while their mutex is held.
 */
readonly class ScheduleRunner
{
    public function __construct(
        private Schedule $schedule,
        private TaskMutexInterface $taskMutex,
    ) {}

    /**
     * @throws SchedulerException When an overlap-protected task has no description, or its mutex cannot be stored
     */
    public function run(
        DateTimeInterface $now,
        Output $output,
    ): ScheduleRunResult {
        $this->assertOverlapProtectedTasksHaveIdentity();

        $dueTasks = $this->schedule->dueTasksAt($now);

        if ($dueTasks === []) {
            $output->writeLine('No scheduled tasks are due.');

            return new ScheduleRunResult(executed: 0, failed: 0, skipped: 0);
        }

        $executed = 0;
        $failed = 0;
        $skipped = 0;
        $skippedByCondition = 0;

        foreach ($dueTasks as $index => $task) {
            $description = $task->getDescription() ?? 'Task ' . ($index + 1);

            try {
                $passesConditions = $task->passesConditions();
            } catch (Throwable $e) {
                $output->writeLine("Failed: $description - " . $e->getMessage());
                $failed++;

                continue;
            }

            if (!$passesConditions) {
                $output->writeLine("Skipped (condition): $description");
                $skippedByCondition++;

                continue;
            }

            $preventsOverlapping = $task->preventsOverlapping();

            if ($preventsOverlapping
                && !$this->taskMutex->acquire($task, (int) $task->getOverlapExpiresAfterMinutes() * 60)
            ) {
                $output->writeLine("Skipped (still running): $description");
                $skipped++;

                continue;
            }

            try {
                $task->run();
                $output->writeLine("Executed: $description");
                $executed++;
            } catch (Throwable $e) {
                $output->writeLine("Failed: $description - " . $e->getMessage());
                $failed++;
            } finally {
                if ($preventsOverlapping) {
                    $this->taskMutex->release($task);
                }
            }
        }

        $output->writeLine("Executed $executed scheduled tasks.");

        if ($failed > 0) {
            $noun = $failed === 1 ? 'task' : 'tasks';
            $output->writeLine("$failed scheduled $noun failed.");
        }

        return new ScheduleRunResult(
            executed: $executed,
            failed: $failed,
            skipped: $skipped,
            skippedByCondition: $skippedByCondition,
        );
    }

    /**
     * Validate every overlap-protected task up front, due or not, so a misconfigured
     * task fails loudly on the first run instead of only when it happens to be due.
     *
     * @throws SchedulerException
     */
    private function assertOverlapProtectedTasksHaveIdentity(): void
    {
        foreach ($this->schedule->tasks() as $task) {
            if ($task->preventsOverlapping()) {
                $task->mutexName();
            }
        }
    }
}
