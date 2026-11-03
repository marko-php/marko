<?php

declare(strict_types=1);

namespace Marko\Scheduler\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class SchedulerException extends MarkoException
{
    public static function overlapRequiresDescription(
        string $expression,
    ): self {
        return new self(
            message: "A scheduled task with expression '$expression' uses withoutOverlapping() but has no description.",
            context: 'While building the overlap mutex name for a scheduled task',
            suggestion: "Closures have no stable identity, so overlap protection keys the mutex on the task's description. Add ->description('Unique task name') to the task.",
        );
    }

    public static function invalidOverlapExpiry(
        int $expiresAfterMinutes,
    ): self {
        return new self(
            message: "withoutOverlapping() expiry must be at least 1 minute, $expiresAfterMinutes given.",
            context: 'While configuring overlap protection for a scheduled task',
            suggestion: 'Pass the number of minutes after which a held mutex is considered stale, e.g. ->withoutOverlapping(60). The default is 1440 (24 hours).',
        );
    }

    public static function invalidTime(
        string $method,
        string $time,
    ): self {
        return new self(
            message: "$method() was given '$time', which is not a valid 24-hour HH:MM time.",
            context: 'While configuring the time of day for a scheduled task',
            suggestion: "Pass the time as hours 0-23 and minutes 00-59 separated by a colon, e.g. ->$method('03:30').",
        );
    }

    public static function invalidHour(
        string $method,
        int $hour,
    ): self {
        return new self(
            message: "$method() was given hour $hour, which is outside 0-23.",
            context: 'While configuring the hours of a scheduled task',
            suggestion: 'Pass hours on the 24-hour clock, from 0 (midnight) to 23.',
        );
    }

    public static function invalidMinute(
        string $method,
        int $minute,
    ): self {
        return new self(
            message: "$method() was given minute $minute, which is outside 0-59.",
            context: 'While configuring the minute of a scheduled task',
            suggestion: 'Pass a minute from 0 to 59.',
        );
    }

    public static function duplicateHour(
        string $method,
        int $hour,
    ): self {
        return new self(
            message: "$method() was given hour $hour twice.",
            context: 'While configuring the hours of a scheduled task',
            suggestion: 'Pass two different hours, e.g. ->twiceDaily(1, 13). To run once a day, use ->dailyAt().',
        );
    }

    public static function invalidDayOfWeek(
        string $method,
        int $dayOfWeek,
    ): self {
        return new self(
            message: "$method() was given day of week $dayOfWeek, which is outside 0-7.",
            context: 'While configuring the day of week of a scheduled task',
            suggestion: 'Pass 0 (Sunday) to 6 (Saturday). 7 is also accepted as Sunday.',
        );
    }

    public static function invalidDayOfMonth(
        string $method,
        int $dayOfMonth,
    ): self {
        return new self(
            message: "$method() was given day of month $dayOfMonth, which is outside 1-31.",
            context: 'While configuring the day of month of a scheduled task',
            suggestion: 'Pass a day of month from 1 to 31. A day that a month does not have (e.g. 31 in April) does not run that month.',
        );
    }

    public static function unknownTimezone(
        string $timezone,
    ): self {
        return new self(
            message: "Unknown time zone '$timezone'.",
            context: 'While configuring the time zone of a scheduled task',
            suggestion: "Pass an IANA time zone identifier such as 'America/New_York' or 'UTC'. DateTimeZone::listIdentifiers() lists them all.",
        );
    }

    public static function conditionMustReturnBool(
        string $method,
        string $taskLabel,
        string $returnedType,
    ): self {
        return new self(
            message: "A $method() condition of scheduled task $taskLabel returned $returnedType instead of a bool.",
            context: 'While checking whether a due scheduled task should run',
            suggestion: "Return true or false from the closure passed to $method(), e.g. ->$method(fn (): bool => \$enabled === true).",
        );
    }

    public static function unknownCommand(
        string $command,
    ): self {
        return new self(
            message: "Schedule::command() was given '$command', which is not a registered command.",
            context: 'While registering a scheduled command task',
            suggestion: 'Check the name against `marko list`. The command must be a #[Command] class in an installed module.',
        );
    }

    /**
     * @param list<string> $undeclaredOptions The refused options, as written (`--day`)
     * @param list<string> $acceptedOptions The options the command accepts, as typed (`--days=<value>`)
     */
    public static function undeclaredCommandOptions(
        string $command,
        array $undeclaredOptions,
        array $acceptedOptions,
    ): self {
        $noun = count($undeclaredOptions) === 1 ? 'option' : 'options';
        $quoted = implode(', ', array_map(fn (string $option): string => "'$option'", $undeclaredOptions));
        $pronoun = count($undeclaredOptions) === 1 ? 'it' : 'them';

        return new self(
            message: "Schedule::command() was given the $noun $quoted for '$command', which does not accept $pronoun.",
            context: 'While registering a scheduled command task',
            suggestion: "'$command' accepts: " . implode(', ', $acceptedOptions) . '. Fix the option name, or'
                . ' declare it on the command in #[Command(options: [...])], or in flags: [...] when it takes no value.',
        );
    }

    public static function invalidCommandArgument(
        string $command,
        string $givenType,
    ): self {
        return new self(
            message: "Scheduled command '$command' was given a $givenType argument; every argument must be a string.",
            context: 'While registering a scheduled command task',
            suggestion: "Pass the arguments as argv-style strings, the way you would type them after the command name, e.g. ->command('$command', ['--days=30', 'users']).",
        );
    }

    public static function backgroundRequiresCommand(
        string $taskLabel,
    ): self {
        return new self(
            message: "Scheduled task $taskLabel is a closure and cannot runInBackground().",
            context: 'While configuring a scheduled task to run in the background',
            suggestion: 'A closure cannot be passed to another process. Move its body into a #[Command] class and schedule it with $schedule->command(\'your:command\')->runInBackground().',
        );
    }

    public static function notAClosureTask(
        string $method,
        string $taskLabel,
    ): self {
        return new self(
            message: "$method() was called on scheduled task $taskLabel, which runs a command, not a closure.",
            context: 'While running a scheduled task',
            suggestion: 'Command tasks are run by ScheduleRunner through the CommandRunner. Use getCommand() to read the command name and arguments.',
        );
    }

    public static function commandFailed(
        string $command,
        int $exitCode,
    ): self {
        return new self(
            message: "Command '$command' exited with code $exitCode.",
            context: 'While running a scheduled command task',
            suggestion: 'A non-zero exit code counts as a failure. Check the command output above for the cause.',
        );
    }

    public static function markoBinaryNotFound(
        string $path,
    ): self {
        return new self(
            message: "The marko binary was not found at '$path', so a background scheduled command cannot be started.",
            context: 'While launching a runInBackground() scheduled task',
            suggestion: 'Install marko/cli in the project (composer require marko/cli) so vendor/bin/marko exists.',
        );
    }

    public static function backgroundLaunchFailed(
        string $command,
        string $reason,
    ): self {
        return new self(
            message: "Could not start scheduled command '$command' in the background: $reason.",
            context: 'While launching a runInBackground() scheduled task',
            suggestion: 'Check that the project directory exists, that proc_open() is not disabled (disable_functions), and that the process limit for the scheduler user is not reached.',
        );
    }

    public static function backgroundMutexOptionMissing(
        string $commandName,
        string $option,
    ): self {
        return new self(
            message: "$commandName requires --$option=<value>.",
            context: "While running $commandName",
            suggestion: "$commandName is started by the scheduler for runInBackground() tasks that use withoutOverlapping(); it is not meant to be run by hand.",
        );
    }

    public static function backgroundCommandMissing(
        string $commandName,
    ): self {
        return new self(
            message: "$commandName was not given a command to run.",
            context: "While running $commandName",
            suggestion: "Pass the command and its arguments after --, e.g. marko $commandName --schedule-mutex=<name> -- app:prune --days=30.",
        );
    }

    public static function invalidMutexName(
        string $name,
    ): self {
        return new self(
            message: "'$name' is not a valid scheduler mutex name.",
            context: 'While locating a scheduled task overlap mutex',
            suggestion: 'Mutex names are file names made of letters, digits, dots, dashes and underscores, as ScheduledTask::mutexName() returns them (schedule-{sha1}).',
        );
    }

    public static function invalidMutexExpiry(
        string $commandName,
        string $option,
        string $given,
    ): self {
        return new self(
            message: "$commandName requires --$option=<seconds> with a whole number of seconds of at least 1, '$given' given.",
            context: "While running $commandName",
            suggestion: "$commandName is started by the scheduler for runInBackground() tasks that use withoutOverlapping(); it is not meant to be run by hand.",
        );
    }

    public static function mutexDirectoryNotWritable(
        string $directory,
    ): self {
        return new self(
            message: "Scheduler mutex directory '$directory' could not be created or is not writable.",
            context: 'While acquiring a scheduled task overlap mutex',
            suggestion: "Create the directory and make it writable by the user running the scheduler, e.g. mkdir -p '$directory'.",
        );
    }

    public static function mutexFileNotOpenable(
        string $path,
    ): self {
        return new self(
            message: "Scheduler mutex file '$path' could not be opened.",
            context: 'While acquiring a scheduled task overlap mutex',
            suggestion: 'Check the permissions of the mutex directory and file for the user running the scheduler.',
        );
    }

    public static function oneServerRequiresDescription(
        string $expression,
    ): self {
        return new self(
            message: "A scheduled task with expression '$expression' uses onOneServer() but has no description.",
            context: 'While validating the scheduled tasks before a run',
            suggestion: "Closures have no stable identity, so the server claim is keyed on the task's description. Add ->description('Unique task name') to the task.",
        );
    }

    public static function lockFactoryUnavailable(
        string $requester,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "$requester, but no cache lock store can be resolved.",
            context: 'While resolving Marko\\Cache\\Contracts\\LockFactoryInterface for cross-server scheduling',
            suggestion: 'Install marko/cache with a distributed lock store: composer require marko/cache marko/cache-redis. A file or array store only excludes one server.',
            previous: $previous,
        );
    }

    public static function lockStoreNotDistributed(
        string $requester,
        string $scope,
    ): self {
        return new self(
            message: "$requester, but the bound cache lock store only excludes callers within one $scope.",
            context: 'While checking the lock store scope for cross-server scheduling',
            suggestion: 'A file or array lock store would run the task on every server. Install marko/cache-redis (composer require marko/cache-redis) and point every server at the same Redis.',
        );
    }
}
