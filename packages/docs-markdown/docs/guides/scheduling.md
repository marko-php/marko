---
title: Task Scheduling
description: Define recurring tasks in PHP and run them with a single cron entry.
---

Marko's scheduler lets you define recurring tasks directly in PHP using a fluent API. Instead of managing individual crontab entries for each task, you register closures on a `Schedule` instance with human-readable frequency methods --- and a single system cron entry runs them all.

## Setup

```bash
composer require marko/scheduler
```

Add a single cron entry to your system that runs every minute:

```
* * * * * cd /path/to/project && marko schedule:run
```

The `schedule:run` command checks all registered tasks and executes those that are due. A task that throws is reported in the output, the remaining due tasks still run, and the command exits `1` so cron and monitoring see the failure.

For local development, or a container with no cron daemon, run `marko schedule:work` instead. It stays in the foreground and runs due tasks at the top of every minute until you stop it with `Ctrl+C`.

## Defining Scheduled Tasks

Register tasks in a module's boot callback by injecting `Schedule` and calling its `call()` method with a closure:

```php title="module.php"
use Marko\Scheduler\Schedule;

return [
    'boot' => function (Schedule $schedule): void {
        $schedule->call(function () {
            // Clean up temp files...
        })->daily()->description('Clean temp files');

        $schedule->call(function () {
            // Send digest emails...
        })->everyFifteenMinutes()->description('Send digest');
    },
];
```

Each `call()` returns a `ScheduledTask` instance with a fluent interface for setting the frequency and description.

## Frequency Options

The scheduler provides shorthand methods for common frequencies:

| Method | Cron Expression | Runs |
|---|---|---|
| `everyMinute()` | `* * * * *` | Every minute |
| `everyFiveMinutes()` | `*/5 * * * *` | Every 5 minutes |
| `everyTenMinutes()` | `*/10 * * * *` | Every 10 minutes |
| `everyFifteenMinutes()` | `*/15 * * * *` | Every 15 minutes |
| `everyThirtyMinutes()` | `*/30 * * * *` | Every 30 minutes |
| `hourly()` | `0 * * * *` | At minute 0 of every hour |
| `daily()` | `0 0 * * *` | At midnight |
| `weekly()` | `0 0 * * 0` | Sunday at midnight |
| `monthly()` | `0 0 1 * *` | First day of the month at midnight |
| `dailyAt('03:30')` / `at('03:30')` | `30 3 * * *` | Every day at 03:30 |
| `twiceDaily(1, 13)` | `0 1,13 * * *` | Every day at 01:00 and 13:00 |
| `weeklyOn(1, '08:00')` | `0 8 * * 1` | Mondays at 08:00 |
| `monthlyOn(15, '14:20')` | `20 14 15 * *` | The 15th of every month at 14:20 |

Times are 24-hour `HH:MM`. An invalid time, day or hour throws `SchedulerException`.

## Time Zones and Conditions

Use `timezone()` to match a task against wall-clock time in a specific zone, and `when()` / `skip()` to decide at run time whether a due task should run:

```php
use Marko\Scheduler\Schedule;

$schedule->call(function () {
    // Prune old records...
})->dailyAt('03:30')
    ->timezone('Europe/Berlin')
    ->description('Prune old records')
    ->skip(fn (): bool => $maintenanceMode->isEnabled());
```

A condition-skipped task prints `Skipped (condition): {description}` and never takes the overlap mutex. Daylight saving changes can skip or repeat a run in the transition hour --- see the [package reference](/docs/packages/scheduler/#time-zones) for the exact rules.

## Custom Cron Expressions

Use `cron()` with a raw 5-field expression for full control:

```php
use Marko\Scheduler\Schedule;

$schedule->call(function () {
    // Runs at 3:30 AM on weekdays
})->cron('30 3 * * 1-5')->description('Weekday report');
```

The expression follows the standard 5-field cron format: `minute hour day-of-month month day-of-week`. Fields support wildcards (`*`), steps (`*/5`), ranges (`1-5`), and lists (`1,15,30`).

## Preventing Overlapping Runs

If a task can run longer than its interval, call `withoutOverlapping()` so a new run is skipped while the previous one is still going:

```php
use Marko\Scheduler\Schedule;

$schedule->call(function () {
    // Import the product feed...
})->everyMinute()->description('Import product feed')->withoutOverlapping();
```

The task must have a `description()`, which keys its mutex. A held mutex is considered stale after 24 hours by default; pass a number of minutes to change that, such as `withoutOverlapping(90)`. The default mutex is a local file lock, so it only protects processes on one server --- see the [package reference](/docs/packages/scheduler/#customization) for swapping in a shared mutex.

## Querying Due Tasks

You can programmatically check which tasks are due at a given time using `dueTasksAt()`:

```php
use Marko\Scheduler\Schedule;
use DateTimeImmutable;

readonly class MaintenanceService
{
    public function __construct(
        private Schedule $schedule,
    ) {}

    public function pendingTasks(): array
    {
        return $this->schedule->dueTasksAt(new DateTimeImmutable());
    }
}
```

This returns an array of `ScheduledTask` instances whose cron expressions match the provided time.

## Testing

Since `Schedule` is a plain class with no external dependencies, you can test scheduled tasks directly by creating an instance, registering tasks, and asserting against the results:

```php
use Marko\Scheduler\Schedule;
use DateTimeImmutable;

it('registers cleanup task as daily', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->daily()->description('Clean temp files');

    $tasks = $schedule->tasks();

    expect($tasks)->toHaveCount(1)
        ->and($tasks[0]->getDescription())->toBe('Clean temp files')
        ->and($tasks[0]->getExpression())->toBe('0 0 * * *');
});

it('runs cleanup at midnight', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): string => 'cleaned')->daily();

    $midnight = new DateTimeImmutable('2026-06-15 00:00:00');
    $noon = new DateTimeImmutable('2026-06-15 12:00:00');

    expect($schedule->dueTasksAt($midnight))->toHaveCount(1)
        ->and($schedule->dueTasksAt($noon))->toBeEmpty();
});
```

You can also verify a task's callback runs correctly by calling `run()` on a `ScheduledTask`:

```php
use Marko\Scheduler\Schedule;

it('executes the task callback', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): string => 'done')->everyMinute();

    $tasks = $schedule->tasks();

    expect($tasks[0]->run())->toBe('done');
});
```

## Related Links

- [Scheduler package reference](/docs/packages/scheduler/) --- full API details
- [Queues](/docs/guides/queues/) --- defer work to background processes
