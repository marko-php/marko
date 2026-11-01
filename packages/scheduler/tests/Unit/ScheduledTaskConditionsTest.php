<?php

declare(strict_types=1);

use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledTask;

it('passes its conditions when it has none', function (): void {
    expect(new ScheduledTask(fn (): null => null)->passesConditions())->toBeTrue();
});

it('passes when every when() condition returns true', function (): void {
    $task = new ScheduledTask(fn (): null => null);

    $result = $task->when(fn (): bool => true)->when(fn (): bool => true);

    expect($result)->toBe($task)
        ->and($task->passesConditions())->toBeTrue();
});

it('fails when any when() condition returns false', function (): void {
    $task = new ScheduledTask(fn (): null => null)
        ->when(fn (): bool => true)
        ->when(fn (): bool => false);

    expect($task->passesConditions())->toBeFalse();
});

it('fails when any skip() condition returns true', function (): void {
    $task = new ScheduledTask(fn (): null => null);

    $result = $task->skip(fn (): bool => false)->skip(fn (): bool => true);

    expect($result)->toBe($task)
        ->and($task->passesConditions())->toBeFalse();
});

it('passes when every skip() condition returns false', function (): void {
    $task = new ScheduledTask(fn (): null => null)->skip(fn (): bool => false);

    expect($task->passesConditions())->toBeTrue();
});

it('requires when() and skip() conditions together to allow the run', function (): void {
    $task = new ScheduledTask(fn (): null => null)
        ->when(fn (): bool => true)
        ->skip(fn (): bool => true);

    expect($task->passesConditions())->toBeFalse();
});

it('evaluates conditions on every check, not once at registration', function (): void {
    $enabled = false;
    $task = new ScheduledTask(fn (): null => null)->when(function () use (&$enabled): bool {
        return $enabled;
    });

    $before = $task->passesConditions();
    $enabled = true;

    expect($before)->toBeFalse()
        ->and($task->passesConditions())->toBeTrue();
});

it('throws when a condition returns something other than a bool', function (): void {
    new ScheduledTask(fn (): null => null)
        ->description('Poll engine')
        ->when(fn (): string => 'modal')
        ->passesConditions();
})->throws(SchedulerException::class, "A when() condition of scheduled task 'Poll engine' returned string");

it('propagates an exception thrown by a condition', function (): void {
    new ScheduledTask(fn (): null => null)
        ->skip(function (): never {
            throw new RuntimeException('Config missing');
        })
        ->passesConditions();
})->throws(RuntimeException::class, 'Config missing');
