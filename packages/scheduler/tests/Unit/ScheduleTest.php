<?php

declare(strict_types=1);

use Marko\Scheduler\Schedule;
use Marko\Scheduler\ScheduledTask;

it('adds task via call method', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null);

    expect($schedule->tasks())->toHaveCount(1);
});

it('returns ScheduledTask for fluent configuration', function (): void {
    $schedule = new Schedule();
    $task = $schedule->call(fn (): null => null);

    expect($task)->toBeInstanceOf(ScheduledTask::class);

    $task->hourly()->description('Test task');

    expect($task->getExpression())->toBe('0 * * * *')
        ->and($task->getDescription())->toBe('Test task');
});

it('finds due tasks at given time', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): string => 'every minute')->everyMinute();

    $now = new DateTimeImmutable('2026-06-15 14:30:00');
    $dueTasks = $schedule->dueTasksAt($now);

    expect($dueTasks)->toHaveCount(1)
        ->and($dueTasks[0]->run())->toBe('every minute');
});

it('excludes non-due tasks', function (): void {
    $schedule = new Schedule();
    // Daily at midnight - won't be due at 14:30
    $schedule->call(fn (): string => 'daily')->daily();

    $now = new DateTimeImmutable('2026-06-15 14:30:00');
    $dueTasks = $schedule->dueTasksAt($now);

    expect($dueTasks)->toBeEmpty();
});

it('detects every minute task as always due', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->everyMinute();

    $time1 = new DateTimeImmutable('2026-06-15 00:00:00');
    $time2 = new DateTimeImmutable('2026-06-15 14:37:00');
    $time3 = new DateTimeImmutable('2026-12-31 23:59:00');

    expect($schedule->dueTasksAt($time1))->toHaveCount(1)
        ->and($schedule->dueTasksAt($time2))->toHaveCount(1)
        ->and($schedule->dueTasksAt($time3))->toHaveCount(1);
});

it('detects hourly task due at top of hour', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->hourly();

    $topOfHour = new DateTimeImmutable('2026-06-15 14:00:00');
    $midHour = new DateTimeImmutable('2026-06-15 14:30:00');

    expect($schedule->dueTasksAt($topOfHour))->toHaveCount(1)
        ->and($schedule->dueTasksAt($midHour))->toBeEmpty();
});

it('detects daily task due at midnight', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->daily();

    $midnight = new DateTimeImmutable('2026-06-15 00:00:00');
    $noon = new DateTimeImmutable('2026-06-15 12:00:00');

    expect($schedule->dueTasksAt($midnight))->toHaveCount(1)
        ->and($schedule->dueTasksAt($noon))->toBeEmpty();
});

it('returns empty array when no tasks are due', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): null => null)->cron('0 0 1 1 *'); // Only Jan 1 at midnight

    $now = new DateTimeImmutable('2026-06-15 14:30:00');

    expect($schedule->dueTasksAt($now))->toBeEmpty();
});

it('handles multiple due tasks', function (): void {
    $schedule = new Schedule();
    $schedule->call(fn (): string => 'task1')->everyMinute();
    $schedule->call(fn (): string => 'task2')->everyMinute();
    $schedule->call(fn (): string => 'task3')->daily(); // Not due at 14:30

    $now = new DateTimeImmutable('2026-06-15 14:30:00');
    $dueTasks = $schedule->dueTasksAt($now);

    expect($dueTasks)->toHaveCount(2)
        ->and($dueTasks[0]->run())->toBe('task1')
        ->and($dueTasks[1]->run())->toBe('task2');
});
