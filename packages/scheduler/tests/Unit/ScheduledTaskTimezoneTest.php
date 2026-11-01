<?php

declare(strict_types=1);

use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledTask;
use Marko\Testing\Fake\FakeClock;

/**
 * Count the minutes in [$fromUtc, $toUtc) at which the task is due.
 *
 * @return array<string> the due instants, formatted in UTC
 */
function dueMinutesBetween(
    ScheduledTask $task,
    string $fromUtc,
    string $toUtc,
): array {
    $clock = new FakeClock(new DateTimeImmutable($fromUtc, new DateTimeZone('UTC')));
    $end = new DateTimeImmutable($toUtc, new DateTimeZone('UTC'));
    $due = [];

    while ($clock->now() < $end) {
        if ($task->isDue($clock->now())) {
            $due[] = $clock->now()->format('Y-m-d H:i');
        }

        $clock->travel('+1 minute');
    }

    return $due;
}

it('matches dailyAt against the time given', function (): void {
    $task = new ScheduledTask(fn (): null => null)->dailyAt('03:30');
    $clock = new FakeClock('2026-10-05 03:30:00');

    expect($task->isDue($clock->now()))->toBeTrue();

    $clock->travel('+1 minute');

    expect($task->isDue($clock->now()))->toBeFalse();
});

it('has no timezone by default and matches in the time zone of the given time', function (): void {
    $task = new ScheduledTask(fn (): null => null)->dailyAt('03:30');
    $now = new DateTimeImmutable('2026-10-05 03:30:00', new DateTimeZone('Asia/Tokyo'));

    expect($task->getTimezone())->toBeNull()
        ->and($task->isDue($now))->toBeTrue();
});

it('accepts a timezone name or a DateTimeZone', function (): void {
    $named = new ScheduledTask(fn (): null => null);
    $object = new ScheduledTask(fn (): null => null);

    $result = $named->timezone('Europe/Paris');
    $object->timezone(new DateTimeZone('Europe/Paris'));

    expect($result)->toBe($named)
        ->and($named->getTimezone()?->getName())->toBe('Europe/Paris')
        ->and($object->getTimezone()?->getName())->toBe('Europe/Paris');
});

it('converts the given time into the task timezone before matching', function (): void {
    $task = new ScheduledTask(fn (): null => null)->dailyAt('09:00')->timezone('Asia/Tokyo');
    $clock = new FakeClock(new DateTimeImmutable('2026-10-05 00:00:00', new DateTimeZone('UTC')));

    expect($task->isDue($clock->now()))->toBeTrue();

    $clock->setNow(new DateTimeImmutable('2026-10-05 09:00:00', new DateTimeZone('UTC')));

    expect($task->isDue($clock->now()))->toBeFalse();
});

it('does not change the expression or mutex name when a timezone is set', function (): void {
    $plain = new ScheduledTask(fn (): null => null)->dailyAt('03:30')->description('Prune');
    $zoned = new ScheduledTask(fn (): null => null)->dailyAt('03:30')->description('Prune')
        ->timezone('America/New_York');

    expect($zoned->getExpression())->toBe('30 3 * * *')
        ->and($zoned->mutexName())->toBe($plain->mutexName());
});

it('rejects an unknown timezone name', function (): void {
    new ScheduledTask(fn (): null => null)->timezone('Mars/Olympus_Mons');
})->throws(SchedulerException::class, "Unknown time zone 'Mars/Olympus_Mons'");

it('skips a time that a spring-forward change jumps over', function (): void {
    // America/New_York jumps from 02:00 EST to 03:00 EDT on 2026-03-08.
    $skipped = new ScheduledTask(fn (): null => null)->dailyAt('02:30')->timezone('America/New_York');
    $after = new ScheduledTask(fn (): null => null)->dailyAt('03:30')->timezone('America/New_York');

    expect(dueMinutesBetween($skipped, '2026-03-08 04:00', '2026-03-09 04:00'))->toBe([])
        ->and(dueMinutesBetween($after, '2026-03-08 04:00', '2026-03-09 04:00'))->toBe(['2026-03-08 07:30']);
});

it('runs twice at a time inside a repeated fall-back hour', function (): void {
    // America/New_York falls back from 02:00 EDT to 01:00 EST on 2026-11-01.
    $task = new ScheduledTask(fn (): null => null)->dailyAt('01:30')->timezone('America/New_York');

    expect(dueMinutesBetween($task, '2026-11-01 04:00', '2026-11-02 05:00'))
        ->toBe(['2026-11-01 05:30', '2026-11-01 06:30']);
});
