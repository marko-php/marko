---
title: marko/scheduler
description: Fluent task scheduler with cron expression support — define recurring tasks in PHP and run them with a single cron entry.
---

Fluent task scheduler with cron expression support --- define recurring tasks in PHP and run them with a single cron entry. Register closures on the `Schedule` with human-readable frequency methods (`daily()`, `hourly()`, `everyFiveMinutes()`) or raw cron expressions. A single system cron entry runs `schedule:run` every minute, and the scheduler determines which tasks are due. No per-task crontab entries needed.

## Installation

```bash
composer require marko/scheduler
```

## Usage

### Defining Scheduled Tasks

Inject `Schedule` and register tasks in a module's boot callback:

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

### Frequency Methods

```php
$schedule->call($callback)->everyMinute();
$schedule->call($callback)->everyFiveMinutes();
$schedule->call($callback)->everyTenMinutes();
$schedule->call($callback)->everyFifteenMinutes();
$schedule->call($callback)->everyThirtyMinutes();
$schedule->call($callback)->hourly();
$schedule->call($callback)->daily();
$schedule->call($callback)->weekly();
$schedule->call($callback)->monthly();
```

### Custom Cron Expressions

Use a raw cron expression for full control:

```php
$schedule->call(function () {
    // Runs at 3:30 AM on weekdays
})->cron('30 3 * * 1-5')->description('Weekday report');
```

Supports standard 5-field cron: `minute hour day-of-month month day-of-week`. Fields support:

| Syntax | Example | Meaning |
|--------|---------|---------|
| Wildcard | `*` | Every value |
| Step | `*/5` | Every 5 units |
| Range | `1-5` | Values 1 through 5 |
| Range+step | `1-5/2` | Values 1, 3, 5 |
| List | `1,15,30` | Values 1, 15, and 30 |
| Combined | `1-5,10` | Values 1–5 and 10 |

**Day-of-week:** Both `0` and `7` represent Sunday. When both day-of-month and day-of-week are restricted (neither is `*`), a day matches if *either* field matches (standard cron OR semantics). An invalid or malformed expression throws `InvalidCronExpressionException` loudly.

### Running the Scheduler

Add a single cron entry to your system:

```
* * * * * cd /path/to/project && marko schedule:run
```

The `schedule:run` command checks all registered tasks and executes those that are due. Tasks that fail throw their exception message to the output without halting the remaining tasks.

### Querying Due Tasks

Programmatically check which tasks are due:

```php
use Marko\Scheduler\Schedule;
use DateTimeImmutable;

public function __construct(
    private readonly Schedule $schedule,
) {}

public function pending(): array
{
    return $this->schedule->dueTasksAt(new DateTimeImmutable());
}
```

## API Reference

### Schedule

```php
public function call(Closure $callback): ScheduledTask;
public function tasks(): array;
public function dueTasksAt(DateTimeInterface $time): array;
```

### ScheduledTask

```php
public function everyMinute(): self;
public function everyFiveMinutes(): self;
public function everyTenMinutes(): self;
public function everyFifteenMinutes(): self;
public function everyThirtyMinutes(): self;
public function hourly(): self;
public function daily(): self;
public function weekly(): self;
public function monthly(): self;
public function cron(string $expression): self;
public function description(string $description): self;
public function getDescription(): ?string;
public function getExpression(): string;
public function getCallback(): Closure;
public function isDue(DateTimeInterface $now): bool;
public function run(): mixed;
```

### CronExpression

```php
use Marko\Scheduler\CronExpression;
use Marko\Scheduler\Exceptions\InvalidCronExpressionException;

public static function matches(string $expression, DateTimeInterface $time): bool;
```

Throws `InvalidCronExpressionException` if the expression does not have exactly 5 fields or contains characters that cannot be parsed. All fields are validated before any matching occurs.
