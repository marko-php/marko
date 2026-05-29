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

Supports standard 5-field cron: `minute hour day-of-month month day-of-week`. Fields support wildcards (`*`), steps (`*/5`), ranges (`1-5`), and lists (`1,15,30`).

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
public static function matches(string $expression, DateTimeInterface $time): bool;
```
