<?php

declare(strict_types=1);

use Marko\Queue\StopCondition\MaxJobsStopCondition;
use Marko\Queue\StopCondition\MaxTimeStopCondition;
use Marko\Queue\StopCondition\MemoryStopCondition;
use Marko\Queue\Worker;
use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;
use Marko\Testing\Fake\FakeClock;

function stopState(
    int $jobsProcessed = 0,
    int $memoryBytes = 0,
    string $startedAt = '2026-01-01 00:00:00',
): WorkerRunState {
    return new WorkerRunState(new DateTimeImmutable($startedAt), $jobsProcessed, $memoryBytes);
}

describe('WorkerStopReason', function (): void {
    it('has a case for every way the worker stops', function (): void {
        expect(array_map(fn (WorkerStopReason $reason): string => $reason->name, WorkerStopReason::cases()))
            ->toBe(['Once', 'MaxJobs', 'Memory', 'MaxTime', 'RestartRequested', 'Signal', 'TimedOut']);
    });

    it('exits with EXIT_TIMED_OUT for TimedOut and 0 for every other reason', function (): void {
        foreach (WorkerStopReason::cases() as $reason) {
            $expected = $reason === WorkerStopReason::TimedOut ? Worker::EXIT_TIMED_OUT : 0;

            expect($reason->exitCode())->toBe($expected);
        }

        expect(WorkerStopReason::TimedOut->exitCode())->toBe(1);
    });

    it('describes each reason in a distinct message', function (WorkerStopReason $reason, string $fragment): void {
        expect($reason->message())->toContain($fragment);
    })->with([
        'once' => [WorkerStopReason::Once, '--once'],
        'max jobs' => [WorkerStopReason::MaxJobs, '--max-jobs'],
        'memory' => [WorkerStopReason::Memory, '--memory'],
        'max time' => [WorkerStopReason::MaxTime, '--max-time'],
        'restart' => [WorkerStopReason::RestartRequested, 'restart'],
        'signal' => [WorkerStopReason::Signal, 'signal'],
        'timed out' => [WorkerStopReason::TimedOut, 'timeout'],
    ]);
});

describe('WorkerRunState', function (): void {
    it('holds when the worker started, the jobs processed and the memory in use', function (): void {
        $state = stopState(jobsProcessed: 3, memoryBytes: 2048);

        expect($state->startedAt->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00')
            ->and($state->jobsProcessed)->toBe(3)
            ->and($state->memoryBytes)->toBe(2048)
            ->and(new ReflectionClass(WorkerRunState::class)->isReadOnly())->toBeTrue();
    });
});

describe('MaxJobsStopCondition', function (): void {
    it('implements WorkerStopConditionInterface', function (): void {
        expect(new MaxJobsStopCondition(1))->toBeInstanceOf(WorkerStopConditionInterface::class);
    });

    it('stops once the worker has processed the maximum number of jobs', function (): void {
        $condition = new MaxJobsStopCondition(3);

        expect($condition->check(stopState(jobsProcessed: 2)))->toBeNull()
            ->and($condition->check(stopState(jobsProcessed: 3)))->toBe(WorkerStopReason::MaxJobs)
            ->and($condition->check(stopState(jobsProcessed: 4)))->toBe(WorkerStopReason::MaxJobs);
    });
});

describe('MemoryStopCondition', function (): void {
    it('stops once memory reaches the limit in megabytes', function (): void {
        $condition = new MemoryStopCondition(128);

        expect($condition->check(stopState(memoryBytes: 128 * 1024 * 1024 - 1)))->toBeNull()
            ->and($condition->check(stopState(memoryBytes: 128 * 1024 * 1024)))->toBe(WorkerStopReason::Memory);
    });
});

describe('MaxTimeStopCondition', function (): void {
    it('stops once the clock is the given number of seconds past the start', function (): void {
        $clock = new FakeClock('2026-01-01 00:59:59');
        $condition = new MaxTimeStopCondition(3600, $clock);

        expect($condition->check(stopState()))->toBeNull();

        $clock->travel('+1 second');

        expect($condition->check(stopState()))->toBe(WorkerStopReason::MaxTime);
    });
});
