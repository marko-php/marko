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
use Marko\Scheduler\ScheduledCommand;

/**
 * Internal: the child process of a runInBackground() task that uses withoutOverlapping().
 *
 * Started by the background launcher as
 * `marko schedule:run-background --schedule-mutex=<name> --schedule-mutex-expires=<seconds> -- <command> <args>`.
 * Everything it needs is on that command line: it does not read the schedule. It takes the named
 * mutex, runs the command, and releases the mutex when the command ends. While the mutex is held
 * by an earlier run it prints "Skipped (still running)" and exits 0. If this process crashes, the
 * operating system drops FileTaskMutex's flock(), so the mutex is never held past the work.
 *
 * @noinspection PhpUnused
 */
#[Command(
    name: self::NAME,
    description: 'Internal: run a background scheduled command while holding its overlap mutex',
    options: [self::MUTEX_OPTION, self::EXPIRY_OPTION],
)]
readonly class RunBackgroundCommand implements CommandInterface
{
    public const string NAME = 'schedule:run-background';

    public const string MUTEX_OPTION = 'schedule-mutex';

    public const string EXPIRY_OPTION = 'schedule-mutex-expires';

    public function __construct(
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

        $expiresAfterSeconds = $this->expiresAfterSeconds($input);
        $arguments = $input->getArguments();

        if ($arguments === []) {
            throw SchedulerException::backgroundCommandMissing(self::NAME);
        }

        $command = new ScheduledCommand(array_shift($arguments), $arguments);

        if (!$this->taskMutex->acquire($mutexName, $expiresAfterSeconds)) {
            $output->writeLine('Skipped (still running): ' . $command->description());

            return 0;
        }

        try {
            return $this->commandRunner->run($command->name, $command->input(), $output);
        } finally {
            $this->taskMutex->release($mutexName);
        }
    }

    /**
     * @throws SchedulerException When the expiry option is missing or not a whole number of seconds of at least 1
     */
    private function expiresAfterSeconds(
        Input $input,
    ): int {
        $value = $input->getOption(self::EXPIRY_OPTION) ?? '';

        if (!ctype_digit($value) || (int) $value < 1) {
            throw SchedulerException::invalidMutexExpiry(self::NAME, self::EXPIRY_OPTION, $value);
        }

        return (int) $value;
    }
}
