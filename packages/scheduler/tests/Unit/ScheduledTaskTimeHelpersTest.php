<?php

declare(strict_types=1);

use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledTask;

function timeHelperTask(): ScheduledTask
{
    return new ScheduledTask(fn (): null => null);
}

it('compiles dailyAt to a daily expression at the given time', function (): void {
    $task = timeHelperTask();

    $result = $task->dailyAt('03:30');

    expect($result)->toBe($task)
        ->and($task->getExpression())->toBe('30 3 * * *');
});

it('accepts a single-digit hour in dailyAt', function (): void {
    expect(timeHelperTask()->dailyAt('7:05')->getExpression())->toBe('5 7 * * *');
});

it('compiles at as an alias of dailyAt', function (): void {
    expect(timeHelperTask()->at('23:59')->getExpression())->toBe('59 23 * * *');
});

it('compiles twiceDaily to both hours at the given minute', function (): void {
    expect(timeHelperTask()->twiceDaily(1, 13)->getExpression())->toBe('0 1,13 * * *')
        ->and(timeHelperTask()->twiceDaily(6, 18, 45)->getExpression())->toBe('45 6,18 * * *');
});

it('compiles weeklyOn to the given day of week and time', function (): void {
    expect(timeHelperTask()->weeklyOn(1, '08:00')->getExpression())->toBe('0 8 * * 1');
});

it('normalises day of week 7 to Sunday 0 in weeklyOn', function (): void {
    expect(timeHelperTask()->weeklyOn(7, '08:00')->getExpression())->toBe('0 8 * * 0');
});

it('compiles monthlyOn to the given day of month and time', function (): void {
    expect(timeHelperTask()->monthlyOn(15, '14:20')->getExpression())->toBe('20 14 15 * *');
});

it('rejects a malformed time', function (string $time): void {
    timeHelperTask()->dailyAt($time);
})->with(['3', '03:3', '03-30', 'noon', '03:30:00', ' 03:30', ''])
    ->throws(SchedulerException::class, 'dailyAt()');

it('rejects an hour out of range', function (): void {
    timeHelperTask()->dailyAt('24:00');
})->throws(SchedulerException::class, "dailyAt() was given '24:00'");

it('rejects a minute out of range', function (): void {
    timeHelperTask()->at('12:60');
})->throws(SchedulerException::class, "at() was given '12:60'");

it('rejects a twiceDaily hour out of range', function (int $first, int $second): void {
    timeHelperTask()->twiceDaily($first, $second);
})->with([[-1, 12], [1, 24]])->throws(SchedulerException::class, 'twiceDaily()');

it('rejects a twiceDaily minute out of range', function (): void {
    timeHelperTask()->twiceDaily(1, 13, 60);
})->throws(SchedulerException::class, 'twiceDaily() was given minute 60');

it('rejects twiceDaily with the same hour twice', function (): void {
    timeHelperTask()->twiceDaily(9, 9);
})->throws(SchedulerException::class, 'twiceDaily() was given hour 9 twice');

it('rejects a day of week outside 0-7', function (int $day): void {
    timeHelperTask()->weeklyOn($day, '08:00');
})->with([-1, 8])->throws(SchedulerException::class, 'weeklyOn() was given day of week');

it('rejects a malformed time in weeklyOn', function (): void {
    timeHelperTask()->weeklyOn(1, '8am');
})->throws(SchedulerException::class, "weeklyOn() was given '8am'");

it('rejects a day of month outside 1-31', function (int $day): void {
    timeHelperTask()->monthlyOn($day, '08:00');
})->with([0, 32])->throws(SchedulerException::class, 'monthlyOn() was given day of month');

it('rejects a malformed time in monthlyOn', function (): void {
    timeHelperTask()->monthlyOn(1, '25:00');
})->throws(SchedulerException::class, "monthlyOn() was given '25:00'");

it('keeps the mutex name derived from the compiled expression', function (): void {
    $helper = timeHelperTask()->dailyAt('03:30')->description('Prune');
    $raw = timeHelperTask()->cron('30 3 * * *')->description('Prune');

    expect($helper->mutexName())->toBe($raw->mutexName());
});
