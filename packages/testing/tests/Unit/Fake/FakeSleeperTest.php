<?php

declare(strict_types=1);

use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Memory\Lock\ArrayLockStore;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Database\Connection\TransactionBackoff;
use Marko\Database\Exceptions\DeadlockException;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeSleeper;

it('implements the core sleeper interface', function (): void {
    expect(new FakeSleeper())->toBeInstanceOf(SleeperInterface::class);
});

it('serves as the sleeper for both cache locks and transaction backoff', function (): void {
    $sleeper = new FakeSleeper();
    $clock = new FakeClock();
    $lock = new LockFactory(new ArrayLockStore($clock), $clock, $sleeper)->create('report', 10);
    $conflict = DeadlockException::fromDriverError(
        new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock'),
        'UPDATE items SET name = ?',
        [],
    );

    $lock->block(1);
    new TransactionBackoff($sleeper)->wait(1, 25, $conflict);

    expect($lock->owner())->not->toBe('')
        ->and($sleeper->sleeps)->toBe([25]);
});

it('records each sleep in milliseconds without sleeping', function (): void {
    $sleeper = new FakeSleeper();
    $started = hrtime(true);

    $sleeper->sleep(5_000);
    $sleeper->sleep(0);

    expect($sleeper->sleeps)->toBe([5_000, 0])
        ->and((hrtime(true) - $started) / 1_000_000)->toBeLessThan(1_000.0);
});

it('passes assertSlept when the recorded delays match', function (): void {
    $sleeper = new FakeSleeper();
    $sleeper->sleep(10);
    $sleeper->sleep(20);

    $sleeper->assertSlept(10, 20);

    expect($sleeper->sleeps)->toBe([10, 20]);
});

it('fails assertSlept when the recorded delays differ', function (): void {
    $sleeper = new FakeSleeper();
    $sleeper->sleep(10);

    expect(fn () => $sleeper->assertSlept(10, 20))
        ->toThrow(AssertionFailedException::class, 'Expected sleeps of [10, 20] ms but slept [10] ms.');
});

it('passes assertNotSlept when nothing was recorded', function (): void {
    $sleeper = new FakeSleeper();

    $sleeper->assertNotSlept();

    expect($sleeper->sleeps)->toBe([]);
});

it('fails assertNotSlept after a sleep', function (): void {
    $sleeper = new FakeSleeper();
    $sleeper->sleep(0);

    expect(fn () => $sleeper->assertNotSlept())
        ->toThrow(AssertionFailedException::class, 'Expected no sleeps but slept [0] ms.');
});

it('forgets recorded sleeps on clear', function (): void {
    $sleeper = new FakeSleeper();
    $sleeper->sleep(10);

    $sleeper->clear();

    $sleeper->assertNotSlept();
    expect($sleeper->sleeps)->toBe([]);
});
