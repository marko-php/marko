<?php

declare(strict_types=1);

namespace Marko\Scheduler\Exceptions;

use Marko\Core\Exceptions\MarkoException;

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
}
