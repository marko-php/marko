<?php

declare(strict_types=1);

use Marko\Scheduler\ScheduledTask;

it('creates ScheduledTask with callable and cron expression', function (): void {
    $task = new ScheduledTask(fn (): string => 'result');

    expect($task->getExpression())->toBe('* * * * *')
        ->and($task->getCallback())->toBeInstanceOf(Closure::class)
        ->and($task->run())->toBe('result');
});

it('sets every minute frequency', function (): void {
    $task = new ScheduledTask(fn (): null => null);
    $task->everyMinute();

    expect($task->getExpression())->toBe('* * * * *');
});

it('sets hourly frequency', function (): void {
    $task = new ScheduledTask(fn (): null => null);
    $task->hourly();

    expect($task->getExpression())->toBe('0 * * * *');
});

it('sets daily frequency', function (): void {
    $task = new ScheduledTask(fn (): null => null);
    $task->daily();

    expect($task->getExpression())->toBe('0 0 * * *');
});

it('sets weekly frequency', function (): void {
    $task = new ScheduledTask(fn (): null => null);
    $task->weekly();

    expect($task->getExpression())->toBe('0 0 * * 0');
});

it('sets custom cron expression', function (): void {
    $task = new ScheduledTask(fn (): null => null);
    $task->cron('15 3 * * 1');

    expect($task->getExpression())->toBe('15 3 * * 1');
});

it('has marko module flag in composer.json', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue()
        ->and($composer['name'])->toBe('marko/scheduler');
});
