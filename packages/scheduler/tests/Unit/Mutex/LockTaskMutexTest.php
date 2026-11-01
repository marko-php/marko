<?php

declare(strict_types=1);

use Marko\Cache\Lock\LockScope;
use Marko\Core\Command\Output;
use Marko\Core\Container\Container;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\Mutex\DistributedLockResolver;
use Marko\Scheduler\Mutex\LockTaskMutex;
use Marko\Scheduler\Mutex\TaskMutexInterface;
use Marko\Scheduler\Tests\Fixtures\SchedulerFactory;
use Marko\Scheduler\Tests\Fixtures\SharedLockStore;
use Marko\Testing\Fake\FakeClock;

beforeEach(function (): void {
    $this->clock = new FakeClock('2026-10-05 12:00:00');
    $this->store = new SharedLockStore($this->clock);
    $this->mutex = fn (): LockTaskMutex => new LockTaskMutex(
        SchedulerFactory::lockResolver($this->store, $this->clock),
    );
});

it('implements TaskMutexInterface', function (): void {
    expect(($this->mutex)())->toBeInstanceOf(TaskMutexInterface::class);
});

it('lets only one of two instances hold the named mutex', function (): void {
    $first = ($this->mutex)();
    $second = ($this->mutex)();

    expect($first->acquire('schedule-abc', 60))->toBeTrue()
        ->and($second->acquire('schedule-abc', 60))->toBeFalse()
        ->and($first->acquire('schedule-abc', 60))->toBeFalse()
        ->and($second->exists('schedule-abc'))->toBeTrue();
});

it('frees the mutex for other instances on release', function (): void {
    $first = ($this->mutex)();
    $second = ($this->mutex)();
    $first->acquire('schedule-abc', 60);

    $first->release('schedule-abc');

    expect($second->exists('schedule-abc'))->toBeFalse()
        ->and($second->acquire('schedule-abc', 60))->toBeTrue();
});

it('reclaims the mutex after it expires', function (): void {
    $first = ($this->mutex)();
    $second = ($this->mutex)();
    $first->acquire('schedule-abc', 60);

    $this->clock->travel('+61 seconds');

    expect($second->exists('schedule-abc'))->toBeFalse()
        ->and($second->acquire('schedule-abc', 60))->toBeTrue();
});

it('does not free a lock another instance took after its own expired', function (): void {
    $first = ($this->mutex)();
    $second = ($this->mutex)();
    $first->acquire('schedule-abc', 60);
    $this->clock->travel('+61 seconds');
    $second->acquire('schedule-abc', 60);

    $first->release('schedule-abc');

    expect($first->exists('schedule-abc'))->toBeTrue()
        ->and(($this->mutex)()->acquire('schedule-abc', 60))->toBeFalse();
});

it('does nothing when releasing a mutex it does not hold', function (): void {
    $first = ($this->mutex)();
    $first->acquire('schedule-abc', 60);

    ($this->mutex)()->release('schedule-abc');

    expect($first->exists('schedule-abc'))->toBeTrue();
});

it('prevents overlap across two runner instances', function (): void {
    $runs = 0;
    $secondRunner = null;
    $secondResult = null;
    $output = new Output(fopen('php://memory', 'r+'));
    $now = new DateTimeImmutable('2026-10-05 12:00:00');
    $longImport = function () use (&$runs, &$secondRunner, &$secondResult, $output, $now): void {
        $runs++;

        // The second server starts the task while the first one is still running it.
        if ($runs === 1) {
            $secondResult = $secondRunner->run($now, $output);
        }
    };
    $firstSchedule = SchedulerFactory::schedule();
    $firstSchedule->call($longImport)->everyMinute()->description('Long import')->withoutOverlapping(5);
    $secondSchedule = SchedulerFactory::schedule();
    $secondSchedule->call($longImport)->everyMinute()->description('Long import')->withoutOverlapping(5);
    $secondRunner = SchedulerFactory::runner($secondSchedule, ($this->mutex)());

    $firstResult = SchedulerFactory::runner($firstSchedule, ($this->mutex)())->run($now, $output);

    expect($runs)->toBe(1)
        ->and($firstResult->executed)->toBe(1)
        ->and($secondResult->skipped)->toBe(1)
        ->and($this->store->heldNames())->toBe([]);
});

it('throws when built without a resolvable lock factory', function (): void {
    expect(fn () => new LockTaskMutex(new DistributedLockResolver(new Container())))
        ->toThrow(
            SchedulerException::class,
            'LockTaskMutex is bound to TaskMutexInterface, but no cache lock store can be resolved.',
        );
});

it('throws when built on a lock store that is not distributed', function (LockScope $scope): void {
    expect(fn () => new LockTaskMutex(
        SchedulerFactory::lockResolver(new SharedLockStore($this->clock, $scope), $this->clock),
    ))->toThrow(SchedulerException::class, "only excludes callers within one $scope->value");
})->with([
    'host' => [LockScope::Host],
    'process' => [LockScope::Process],
]);
