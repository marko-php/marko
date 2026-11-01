<?php

declare(strict_types=1);

namespace Marko\Scheduler\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Exceptions\CommandException;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduledCommand;
use Marko\Scheduler\ScheduledTask;

/**
 * Internal: the child process of a runInBackground() task that uses withoutOverlapping().
 *
 * Started by the background launcher as
 * `marko schedule:run-background --schedule-mutex=<name> -- <command> <args>`. It takes the
 * task's overlap mutex, runs the command, and releases the mutex when the command ends. While
 * the mutex is held by an earlier run it prints "Skipped (still running)" and exits 0. If this
 * process crashes, the operating system drops FileTaskMutex's flock(), so the mutex is never
 * held past the work.
 *
 * @noinspection PhpUnused
 */
#[Command(
    name: self::NAME,
    description: 'Internal: run a background scheduled command while holding its overlap mutex',
)]
readonly class RunBackgroundCommand implements CommandInterface
{
    public const string NAME = 'schedule:run-background';

    public const string MUTEX_OPTION = 'schedule-mutex';

    public function __construct(
        private Schedule $schedule,
        private TaskMutexInterface $taskMutex,
        private CommandRunner $commandRunner,
    ) {}

    /**
     * @return int The command's exit code, or 0 when it was skipped
     * @throws SchedulerException|CommandException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $mutexName = $input->getOption(self::MUTEX_OPTION);

        if ($mutexName === null || $mutexName === 'true') {
            throw SchedulerException::backgroundMutexOptionMissing(self::NAME, self::MUTEX_OPTION);
        }

        $arguments = $input->getArguments();

        if ($arguments === []) {
            throw SchedulerException::backgroundCommandMissing(self::NAME);
        }

        $command = new ScheduledCommand(array_shift($arguments), $arguments);
        $task = $this->findTask($mutexName);

        if (!$this->taskMutex->acquire($task, (int) $task->getOverlapExpiresAfterMinutes() * 60)) {
            $output->writeLine('Skipped (still running): ' . $task->getDescription());

            return 0;
        }

        try {
            return $this->commandRunner->run($command->name, $command->input(), $output);
        } finally {
            $this->taskMutex->release($task);
        }
    }

    /**
     * @throws SchedulerException When no overlap-protected task has this mutex name
     */
    private function findTask(
        string $mutexName,
    ): ScheduledTask {
        foreach ($this->schedule->tasks() as $task) {
            if ($task->preventsOverlapping() && $task->mutexName() === $mutexName) {
                return $task;
            }
        }

        throw SchedulerException::unknownBackgroundMutex($mutexName);
    }
}
