<?php

declare(strict_types=1);

namespace Marko\Scheduler\Process;

use Marko\Scheduler\Command\RunBackgroundCommand;
use Marko\Scheduler\Exceptions\SchedulerException;

/**
 * Starts `php vendor/bin/marko <command> <args>` detached, with proc_open().
 *
 * The command is started through `/bin/sh -c '... &'`: the shell puts it in the background and
 * exits at once, so launch() returns without waiting and the child is re-parented to init (no
 * zombie is left behind under a long-running schedule:work). Every token is shell-escaped.
 *
 * The child inherits the parent's stdout and stderr, so whatever captures the scheduler's output
 * (cron mail, systemd's journal, a container log) also captures the command's output and errors.
 *
 * With a mutex name, the child runs `schedule:run-background --schedule-mutex=<name> -- <command> <args>`,
 * which holds the overlap mutex for as long as the command runs.
 */
readonly class ProcOpenBackgroundCommandLauncher implements BackgroundCommandLauncherInterface
{
    public function __construct(
        private string $phpBinary,
        private string $markoBinary,
        private string $workingDirectory,
    ) {}

    /**
     * @param list<string> $arguments
     * @throws SchedulerException When the marko binary is missing or the process cannot be started
     */
    public function launch(
        string $command,
        array $arguments,
        ?string $mutexName,
    ): void {
        if (!is_file($this->markoBinary)) {
            throw SchedulerException::markoBinaryNotFound($this->markoBinary);
        }

        $argv = [$this->phpBinary, $this->markoBinary];

        if ($mutexName !== null) {
            array_push(
                $argv,
                RunBackgroundCommand::NAME,
                '--' . RunBackgroundCommand::MUTEX_OPTION . '=' . $mutexName,
                '--',
            );
        }

        array_push($argv, $command, ...$arguments);

        $shellCommand = implode(' ', array_map(escapeshellarg(...), $argv)) . ' < /dev/null &';
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ];

        $error = null;
        set_error_handler(static function (int $level, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });

        try {
            $process = proc_open(['/bin/sh', '-c', $shellCommand], $descriptors, $pipes, $this->workingDirectory);
        } finally {
            restore_error_handler();
        }

        if ($process === false) {
            throw SchedulerException::backgroundLaunchFailed($command, $error ?? 'proc_open() failed');
        }

        // Waits only for the shell, which exits as soon as it has put the command in the background.
        $shellExitCode = proc_close($process);

        if ($shellExitCode !== 0) {
            throw SchedulerException::backgroundLaunchFailed(
                $command,
                "the launching shell exited with code $shellExitCode",
            );
        }
    }
}
