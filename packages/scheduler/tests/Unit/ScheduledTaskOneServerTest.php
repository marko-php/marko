<?php

declare(strict_types=1);

use Marko\Scheduler\ScheduledTask;

it('runs on every server by default', function (): void {
    $task = new ScheduledTask(fn (): null => null);

    expect($task->runsOnOneServer())->toBeFalse();
});

it('marks the task to run on one server and returns itself for chaining', function (): void {
    $task = new ScheduledTask(fn (): null => null);

    expect($task->onOneServer())->toBe($task)
        ->and($task->runsOnOneServer())->toBeTrue();
});
