---
title: marko/scheduler
description: Fluent task scheduler with cron expression support — define recurring tasks in PHP and run them with a single cron entry.
---

Fluent task scheduler with cron expression support --- define recurring tasks in PHP and run them with a single cron entry. Register closures and console commands on the `Schedule` with human-readable frequency methods (`daily()`, `dailyAt('03:30')`, `everyFiveMinutes()`) or raw cron expressions, optionally in their own time zone and behind `when()`/`skip()` conditions. A single system cron entry runs `schedule:run` every minute, and the scheduler determines which tasks are due. No per-task crontab entries needed. Long-running tasks can be protected from overlapping runs, commands can run in the background so they never hold up the rest of the minute, and `schedule:work` runs the scheduler in the foreground where no cron daemon is available.

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

`Schedule` is registered as a singleton by `marko/scheduler`, so the instance your boot callback fills is the same one `schedule:run` and `schedule:work` read from. Boot callbacks run at the end of application initialization, before any command executes.

Give every closure task a `description()`. It labels the task in command output and is required for overlap protection (see below).

### Scheduling Commands

`command()` schedules a registered console command by name, with argv-style arguments written the way you would type them after the command name:

```php title="module.php"
use Marko\Scheduler\Schedule;

return [
    'boot' => function (Schedule $schedule): void {
        $schedule->command('app:poll-engine-calls')->everyMinute()->withoutOverlapping();

        $schedule->command('app:prune', ['--days=30', 'sessions'])->dailyAt('03:30');
    },
];
```

- **Unknown commands fail at registration.** The name (or an alias) is checked against core's `CommandRegistry` when the schedule is built. An unknown name throws `SchedulerException` naming it, so a typo surfaces on the next boot, not when the task first comes due. An argument that is not a string also throws.
- **Commands run in the scheduler process.** A due command task goes through core's `CommandRunner`, with no subprocess. Its output is written to the scheduler's output, and its exit code decides the result: `0` prints `Executed: {description}`, anything else prints `Failed: {description} - Command '{name}' exited with code {code}.` and counts as a failure. A command that throws is a failure too.
- **The command is the default description.** Without a `description()`, a command task is described by its name and arguments (`app:prune --days=30 sessions`), so `withoutOverlapping()` works without one. An explicit `description()` still wins.

Closure tasks registered with `call()` behave exactly as before.

### Running Commands in the Background

By default, due tasks run one after another, so a long task (a nightly prune, say) delays every task after it in the same minute, and under `schedule:work` it can push the next minute back too. `runInBackground()` starts a command task in a separate process and moves on at once:

```php
$schedule->command('app:prune', ['--days=30'])
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->runInBackground();
```

The run prints `Launched: {description}` instead of `Executed`, counts it in `ScheduleRunResult::$launched`, and ends with `Launched {n} background task(s).` The launch is all the parent sees: the command's own exit code does not affect the `schedule:run` exit code.

**Only commands can run in the background.** A closure cannot be handed to another process, so calling `runInBackground()` on a `call()` task throws `SchedulerException` as soon as the schedule is built. To run closure work in the background, move it into a command:

```php title="app/maintenance/src/Command/PruneCommand.php"
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'app:prune', description: 'Prune old records')]
readonly class PruneCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        // The body of the old closure...

        return 0;
    }
}
```

Then replace `$schedule->call(...)` with `$schedule->command('app:prune')->runInBackground()`.

**Where the output goes.** The background process runs `php vendor/bin/marko {command} {arguments}` from the project root, detached, with every argument shell-escaped. It inherits the scheduler's stdout and stderr, so whatever captures the scheduler's output (cron mail, the systemd journal, a container log) also captures the command's output and errors. Redirect the cron entry's output (`marko schedule:run >> storage/logs/schedule.log 2>&1`) to keep it in a file. The project must have `vendor/bin/marko` (installed by `marko/cli`); without it the launch fails.

**How overlap protection works in the background.** The parent does not take the mutex of a background task. When the task uses `withoutOverlapping()`, the child process is started as `marko schedule:run-background --schedule-mutex={name} -- {command} {arguments}`. That internal command rebuilds the schedule from your boot callbacks, finds the task by its mutex name, takes the mutex, runs the command, and releases the mutex when the command ends. If the mutex is still held by an earlier run, the child prints `Skipped (still running): {description}` and exits `0`. If the child crashes, the operating system drops `FileTaskMutex`'s lock, so the mutex is never held past the work. Because the child looks the task up by its mutex name, register background tasks unconditionally in a boot callback (use `when()`/`skip()` for run-time decisions); if the child cannot find the task it fails loudly.

**What runs in the parent.** The `when()`/`skip()` conditions are evaluated in the scheduler process before launching, so a condition-skipped task never starts a process. A launch failure (for example when `proc_open()` is disabled or the process limit is reached) prints `Failed: {description} - {message}`, counts as a failure, and the remaining due tasks still run.

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

### Times of Day

To run at a time other than midnight, pass a 24-hour `HH:MM` time:

```php
$schedule->call($callback)->dailyAt('03:30');        // 30 3 * * *
$schedule->call($callback)->at('03:30');             // alias of dailyAt()
$schedule->call($callback)->twiceDaily(1, 13);       // 0 1,13 * * *
$schedule->call($callback)->twiceDaily(1, 13, 15);   // 15 1,13 * * *
$schedule->call($callback)->weeklyOn(1, '08:00');    // Mondays: 0 8 * * 1
$schedule->call($callback)->monthlyOn(15, '14:20');  // 15th: 20 14 15 * *
```

Each helper compiles to a 5-field cron expression, so `getExpression()` returns the same string you would get from `cron()`. The arguments are explicit; there are no default times.

`weeklyOn()` takes `0` (Sunday) to `6` (Saturday), and `7` is also accepted as Sunday. `monthlyOn()` takes `1` to `31`; a month without that day (for example the 31st in April) has no run. `twiceDaily()` takes two different hours (0-23) and a minute that defaults to `0`.

A malformed time (`'3pm'`, `'03:3'`), an hour or minute out of range, a day of week outside 0-7, a day of month outside 1-31 or the same `twiceDaily()` hour twice throws `SchedulerException`, which names the method and the value it was given.

### Time Zones

By default a task matches against the time it is checked at, in that time's own zone (the zone of the injected clock, normally PHP's default time zone). Call `timezone()` to match against wall-clock time in another zone:

```php
$schedule->call(function () {
    // Send the morning report...
})->dailyAt('09:00')->timezone('America/New_York')->description('Morning report');
```

`timezone()` takes an IANA zone name or a `DateTimeZone`. An unknown zone name throws `SchedulerException`. The zone is not part of the cron expression: `isDue()` converts the time it is given into the task's zone before matching, so `getExpression()` and the overlap mutex name stay the same.

Because matching uses wall-clock time in the task's zone, daylight saving changes have these effects:

- **Spring forward:** a time the clocks skip (for example `02:30` in `America/New_York` on the day clocks jump from 02:00 to 03:00) does not exist that day, so the task does not run that day.
- **Fall back:** a time inside the repeated hour (for example `01:30` on the day clocks go back from 02:00 to 01:00) happens twice, so the task runs twice. `withoutOverlapping()` does not prevent the second run, because the first one has already finished.

To avoid both, schedule outside the transition hour (usually 01:00-03:00 local time) or use `timezone('UTC')`.

### Conditions

`when()` runs a due task only if its closure returns `true`. `skip()` skips a due task if its closure returns `true`:

```php
$schedule->call(function () {
    // Poll the engine for finished calls...
})->everyMinute()
    ->description('Poll engine calls')
    ->when(fn (): bool => $config->getString('engine.driver') === 'modal')
    ->skip(fn (): bool => $maintenance->isEnabled());
```

Both can be called more than once, and every condition must allow the run. Conditions are evaluated each time the task is due, at run time, not when the task is registered. A condition must return a `bool`; any other return value throws `SchedulerException`.

The runner checks conditions after the task is due and before it acquires the overlap mutex, so a skipped task never takes the mutex. A skipped task prints `Skipped (condition): {description}` and is counted in `ScheduleRunResult::$skippedByCondition`, separately from the overlap skips counted in `$skipped`. A condition that throws (or returns a non-`bool`) counts as a failure of that task: it prints `Failed: {description} - {message}`, makes the run exit `1`, and the remaining due tasks still run.

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

### Preventing Overlapping Runs

A task that can take longer than its interval --- for example a 90-second import scheduled `everyMinute()` --- would otherwise start a second copy while the first is still running. Call `withoutOverlapping()` to skip the task while a previous run holds its mutex:

```php
$schedule->call(function () {
    // Import the product feed...
})->everyMinute()->description('Import product feed')->withoutOverlapping();
```

While the mutex is held, the run prints `Skipped (still running): Import product feed` and moves on. The mutex is released when the task finishes, including when it throws. For a `runInBackground()` task the child process holds the mutex instead (see [Running Commands in the Background](#running-commands-in-the-background)).

`withoutOverlapping()` takes the number of minutes after which a held mutex is considered stale, for when its holder hung. It defaults to `1440` (24 hours):

```php
$schedule->call($callback)->hourly()->description('Rebuild search index')->withoutOverlapping(90);
```

The mutex is keyed on the task's expression and description, because closures have no stable identity. A command task's description defaults to its command name and arguments. A closure task that uses `withoutOverlapping()` without a `description()` makes `schedule:run` and `schedule:work` throw `SchedulerException` before any task runs, whether or not that task is due. An expiry below 1 minute also throws `SchedulerException`.

The default `FileTaskMutex` holds an exclusive `flock()` on `storage/framework/schedule-{hash}` under the project root while the task runs, and records the expiry time in that file. If the process crashes, the operating system releases the lock. A lock that is still held past its expiry is reclaimed by the next run. File locks only protect processes on the same server --- see [Customization](#customization) for multi-server setups.

### Running the Scheduler

Add a single cron entry to your system:

```
* * * * * cd /path/to/project && marko schedule:run
```

The `schedule:run` command checks all registered tasks and executes those that are due. A task that throws, or a command task that exits non-zero, is reported as `Failed: {description} - {message}` and the remaining due tasks still run.

| Exit code | Meaning |
|-----------|---------|
| `0` | Every due task succeeded or was skipped (still running, or by a condition), or no tasks were due |
| `1` | At least one task or one of its conditions threw, a command task exited non-zero, or a background task could not be launched |

The non-zero exit code makes failures visible to cron mail and monitoring tools.

### Running in the Foreground

For local development, or containers without a cron daemon, run the scheduler as a long-lived foreground process:

```bash
marko schedule:work
```

`schedule:work` sleeps until the top of each minute, then runs the tasks due at that minute, using the same logic as `schedule:run`. A failing task is reported and the loop keeps going. Tasks run sequentially in the same process (except `runInBackground()` commands), so if a run takes longer than a minute, the scheduler waits for the next minute boundary rather than catching up on the minutes it missed.

Stop it with `Ctrl+C` (SIGINT) or SIGTERM. With the `pcntl` extension installed, the signal is handled gracefully: the current task finishes, the command prints `Scheduler stopped.` and exits `0`. Without `pcntl`, the signal terminates the process immediately.

### Querying Due Tasks

Programmatically check which tasks are due:

```php
use Marko\Scheduler\Schedule;
use Psr\Clock\ClockInterface;

public function __construct(
    private readonly Schedule $schedule,
    private readonly ClockInterface $clock,
) {}

public function pending(): array
{
    return $this->schedule->dueTasksAt($this->clock->now());
}
```

### Running Due Tasks Programmatically

`ScheduleRunner` is the service both commands use. Call it directly to run the tasks due at a specific time:

```php
use Marko\Core\Command\Output;
use Marko\Scheduler\ScheduleRunner;
use Psr\Clock\ClockInterface;

public function __construct(
    private readonly ScheduleRunner $scheduleRunner,
    private readonly ClockInterface $clock,
) {}

public function runNow(Output $output): bool
{
    $result = $this->scheduleRunner->run($this->clock->now(), $output);

    return !$result->hasFailures();
}
```

### Time and Testing

`schedule:run`, `schedule:work` and `FileTaskMutex` read the current time from the injected `Psr\Clock\ClockInterface` ([`marko/clock`](/docs/packages/clock/)): `schedule:run` runs the tasks due at the clock's time, `schedule:work` sleeps until the clock reaches the next minute, and the mutex writes and checks its expiry against the clock. To test a schedule at a given time, construct the command with a [`FakeClock`](/docs/packages/testing/#fakeclock):

```php
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Scheduler\Command\RunScheduleCommand;
use Marko\Scheduler\Mutex\FileTaskMutex;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\ScheduleRunner;
use Marko\Testing\Fake\FakeClock;

it('sends the report on the first of the month', function (): void {
    $clock = new FakeClock('2026-11-01 08:00:00');
    $runner = new ScheduleRunner(
        $schedule,
        new FileTaskMutex(sys_get_temp_dir() . '/mutex', $clock),
        $container->get(CommandRunner::class),
        $container->get(BackgroundCommandLauncherInterface::class),
    );
    $command = new RunScheduleCommand($runner, $clock);

    $command->execute(new Input(['marko', 'schedule:run']), new Output(fopen('php://memory', 'r+')));

    expect($reportSender->sent)->toBeTrue();
});
```

## Customization

### Background Launcher

`runInBackground()` tasks are started through `BackgroundCommandLauncherInterface`, which `marko/scheduler` binds to `ProcOpenBackgroundCommandLauncher`. To start them another way (a job runner, a container API), implement the interface and bind it from your application module. `launch()` must return without waiting for the command, and when it is given a mutex name the child must hold that mutex while the command runs, as `schedule:run-background` does.

### Shared Mutex

Overlap protection depends on `TaskMutexInterface`, which `marko/scheduler` binds to `FileTaskMutex`. To share the mutex across several servers, implement the interface on a shared store such as Redis and bind it from your application module, which takes priority over the vendor binding:

```php title="app/scheduling/module.php"
use App\Scheduling\RedisTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;

return [
    'bindings' => [
        TaskMutexInterface::class => RedisTaskMutex::class,
    ],
];
```

`acquire()` must be atomic and non-blocking, and must treat a mutex older than `$expiresAfterSeconds` as free.

## API Reference

### Schedule

```php
public function __construct(CommandRegistry $commandRegistry);
public function call(Closure $callback): ScheduledTask;
public function command(string $name, array $arguments = []): ScheduledTask;
public function tasks(): array;
public function dueTasksAt(DateTimeInterface $time): array;
```

`command()` throws `SchedulerException` when the command is not registered or an argument is not a string.

### ScheduledTask

```php
public function __construct(Closure|ScheduledCommand $action);
public function everyMinute(): self;
public function everyFiveMinutes(): self;
public function everyTenMinutes(): self;
public function everyFifteenMinutes(): self;
public function everyThirtyMinutes(): self;
public function hourly(): self;
public function daily(): self;
public function weekly(): self;
public function monthly(): self;
public function dailyAt(string $time): self;
public function at(string $time): self;
public function twiceDaily(int $firstHour, int $secondHour, int $minute = 0): self;
public function weeklyOn(int $dayOfWeek, string $time): self;
public function monthlyOn(int $dayOfMonth, string $time): self;
public function cron(string $expression): self;
public function timezone(DateTimeZone|string $timezone): self;
public function getTimezone(): ?DateTimeZone;
public function when(Closure $condition): self;
public function skip(Closure $condition): self;
public function passesConditions(): bool;
public function description(string $description): self;
public function withoutOverlapping(int $expiresAfterMinutes = 1440): self;
public function preventsOverlapping(): bool;
public function getOverlapExpiresAfterMinutes(): ?int;
public function mutexName(): string;
public function getDescription(): ?string;
public function getExpression(): string;
public function runInBackground(): self;
public function runsInBackground(): bool;
public function getCommand(): ?ScheduledCommand;
public function getCallback(): Closure;
public function isDue(DateTimeInterface $now): bool;
public function run(): mixed;
```

`getDescription()` returns the command name and arguments for a command task without a description. `runInBackground()` throws `SchedulerException` on a closure task. `getCallback()` and `run()` throw `SchedulerException` on a command task, which `ScheduleRunner` runs through the `CommandRunner`.

`mutexName()` throws `SchedulerException` when a closure task has no description. `withoutOverlapping()` throws `SchedulerException` when the expiry is below 1 minute. The time helpers throw `SchedulerException` for an invalid time, day or hour, `timezone()` for an unknown zone name, and `passesConditions()` when a condition returns something other than a `bool` (an exception thrown by a condition propagates as is).

### ScheduledCommand

```php
use Marko\Core\Command\Input;

public string $name;
public array $arguments;
public function description(): string;
public function input(): Input;
```

### ScheduleRunner

```php
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\Output;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Process\BackgroundCommandLauncherInterface;
use Marko\Scheduler\ScheduleRunResult;

public function __construct(Schedule $schedule, TaskMutexInterface $taskMutex, CommandRunner $commandRunner, BackgroundCommandLauncherInterface $backgroundCommandLauncher);
public function run(DateTimeInterface $now, Output $output): ScheduleRunResult;
```

### ScheduleRunResult

```php
public int $executed;
public int $failed;
public int $skipped;            // skipped because a previous run still holds the mutex
public int $skippedByCondition; // skipped by a when()/skip() condition
public int $launched;           // runInBackground() tasks handed to the launcher
public function hasFailures(): bool;
```

### TaskMutexInterface

```php
use Marko\Scheduler\ScheduledTask;

public function acquire(ScheduledTask $task, int $expiresAfterSeconds): bool;
public function release(ScheduledTask $task): void;
public function exists(ScheduledTask $task): bool;
```

### BackgroundCommandLauncherInterface

```php
public function launch(string $command, array $arguments, ?string $mutexName): void;
```

`ProcOpenBackgroundCommandLauncher` throws `SchedulerException` when `vendor/bin/marko` is missing or the process cannot be started.

### CronExpression

```php
use Marko\Scheduler\CronExpression;
use Marko\Scheduler\Exceptions\InvalidCronExpressionException;

public static function matches(string $expression, DateTimeInterface $time): bool;
```

Throws `InvalidCronExpressionException` if the expression does not have exactly 5 fields or contains characters that cannot be parsed. All fields are validated before any matching occurs.

### Commands

| Command | Description |
|---------|-------------|
| `marko schedule:run` | Run the tasks due this minute; exits `1` if any task failed |
| `marko schedule:work` | Run due tasks at the top of every minute in the foreground until stopped |
| `marko schedule:run-background` | Internal: the child process of a `runInBackground()` task that uses `withoutOverlapping()` |
