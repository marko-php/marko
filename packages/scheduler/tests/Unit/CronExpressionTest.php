<?php

declare(strict_types=1);

use Marko\Scheduler\CronExpression;
use Marko\Scheduler\Exceptions\InvalidCronExpressionException;

describe('CronExpression', function (): void {
    it('matches wildcard for all values', function (): void {
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        expect(CronExpression::matches('* * * * *', $time))->toBeTrue();
    });

    it('matches specific minute value', function (): void {
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        expect(CronExpression::matches('30 * * * *', $time))->toBeTrue()
            ->and(CronExpression::matches('15 * * * *', $time))->toBeFalse();
    });

    it('matches specific hour value', function (): void {
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        expect(CronExpression::matches('* 14 * * *', $time))->toBeTrue()
            ->and(CronExpression::matches('* 10 * * *', $time))->toBeFalse();
    });

    it('matches step values', function (): void {
        $time0 = new DateTimeImmutable('2026-06-15 14:00:00');
        $time5 = new DateTimeImmutable('2026-06-15 14:05:00');
        $time10 = new DateTimeImmutable('2026-06-15 14:10:00');
        $time3 = new DateTimeImmutable('2026-06-15 14:03:00');

        expect(CronExpression::matches('*/5 * * * *', $time0))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time5))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time10))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time3))->toBeFalse();
    });

    it('matches comma-separated values', function (): void {
        $time1 = new DateTimeImmutable('2026-06-15 14:01:00');
        $time15 = new DateTimeImmutable('2026-06-15 14:15:00');
        $time30 = new DateTimeImmutable('2026-06-15 14:30:00');
        $time7 = new DateTimeImmutable('2026-06-15 14:07:00');

        expect(CronExpression::matches('1,15,30 * * * *', $time1))->toBeTrue()
            ->and(CronExpression::matches('1,15,30 * * * *', $time15))->toBeTrue()
            ->and(CronExpression::matches('1,15,30 * * * *', $time30))->toBeTrue()
            ->and(CronExpression::matches('1,15,30 * * * *', $time7))->toBeFalse();
    });

    it('matches range values', function (): void {
        $time1 = new DateTimeImmutable('2026-06-15 14:01:00');
        $time3 = new DateTimeImmutable('2026-06-15 14:03:00');
        $time5 = new DateTimeImmutable('2026-06-15 14:05:00');
        $time7 = new DateTimeImmutable('2026-06-15 14:07:00');

        expect(CronExpression::matches('1-5 * * * *', $time1))->toBeTrue()
            ->and(CronExpression::matches('1-5 * * * *', $time3))->toBeTrue()
            ->and(CronExpression::matches('1-5 * * * *', $time5))->toBeTrue()
            ->and(CronExpression::matches('1-5 * * * *', $time7))->toBeFalse();
    });

    it('rejects non-matching values', function (): void {
        $time = new DateTimeImmutable('2026-06-15 14:30:00');

        expect(CronExpression::matches('0 0 1 1 1', $time))->toBeFalse();
    });

    it('matches Sunday when the day-of-week field is 7', function (): void {
        // 2026-06-14 is a Sunday (PHP w=0)
        $sunday = new DateTimeImmutable('2026-06-14 10:00:00');

        expect(CronExpression::matches('* * * * 7', $sunday))->toBeTrue();
    });

    it('matches Sunday when the day-of-week field is 0', function (): void {
        // 2026-06-14 is a Sunday (PHP w=0)
        $sunday = new DateTimeImmutable('2026-06-14 10:00:00');

        expect(CronExpression::matches('* * * * 0', $sunday))->toBeTrue();
    });

    it('matches a value present in a combined list-and-range field like 1-5,10', function (): void {
        $time3 = new DateTimeImmutable('2026-06-15 14:03:00');
        $time10 = new DateTimeImmutable('2026-06-15 14:10:00');
        $time7 = new DateTimeImmutable('2026-06-15 14:07:00');

        expect(CronExpression::matches('1-5,10 * * * *', $time3))->toBeTrue()
            ->and(CronExpression::matches('1-5,10 * * * *', $time10))->toBeTrue()
            ->and(CronExpression::matches('1-5,10 * * * *', $time7))->toBeFalse();
    });

    it('throws a loud exception for an expression that does not have five fields', function (): void {
        expect(fn () => CronExpression::matches('* * * *', new DateTimeImmutable()))
            ->toThrow(InvalidCronExpressionException::class);
    });

    it('throws a loud exception for an unparseable field value', function (): void {
        expect(fn () => CronExpression::matches('abc * * * *', new DateTimeImmutable()))
            ->toThrow(InvalidCronExpressionException::class);
    });

    it('matches Sunday from a day-of-week list containing the 7 alias like 0,7', function (): void {
        // 2026-06-14 is a Sunday (PHP w=0)
        $sunday = new DateTimeImmutable('2026-06-14 00:00:00');
        // 2026-06-15 is a Monday (PHP w=1)
        $monday = new DateTimeImmutable('2026-06-15 00:00:00');

        // 0,7 should match Sunday (both 0 and 7 alias to Sunday)
        expect(CronExpression::matches('* * * * 0,7', $sunday))->toBeTrue()
            ->and(CronExpression::matches('* * * * 0,7', $monday))->toBeFalse();
    });

    it('ANDs day-of-month and day-of-week when only one of them is restricted', function (): void {
        // Expression: run on the 15th of any month, any day of week
        // 2026-06-15 is a Monday (w=1) — DOM matches, DOW=* → should match
        $monday15 = new DateTimeImmutable('2026-06-15 00:00:00');
        // 2026-06-14 is a Sunday (w=0) — DOM=14 ≠ 15, DOW=* → should NOT match
        $sunday14 = new DateTimeImmutable('2026-06-14 00:00:00');

        expect(CronExpression::matches('0 0 15 * *', $monday15))->toBeTrue()
            ->and(CronExpression::matches('0 0 15 * *', $sunday14))->toBeFalse();

        // Expression: run every Monday, any day of month
        // 2026-06-15 is a Monday (w=1) — DOM=*, DOW=1 → should match
        $monday = new DateTimeImmutable('2026-06-15 00:00:00');
        // 2026-06-16 is a Tuesday (w=2) — DOM=*, DOW=2 ≠ 1 → should NOT match
        $tuesday = new DateTimeImmutable('2026-06-16 00:00:00');

        expect(CronExpression::matches('0 0 * * 1', $monday))->toBeTrue()
            ->and(CronExpression::matches('0 0 * * 1', $tuesday))->toBeFalse();
    });

    it(
        'matches when either a restricted day-of-month or a restricted day-of-week matches (OR semantics)',
        function (): void {
            // 2026-06-14 is Sunday (w=0), day-of-month=14
            // Expression: run on day 15 OR on Sunday
            // Sunday (14th): DOM=14 ≠ 15, DOW=0=Sunday → should match via OR
            $sunday14 = new DateTimeImmutable('2026-06-14 00:00:00');
            // Monday 15th: DOM=15 matches, DOW=1 ≠ Sunday → should match via OR
            $monday15 = new DateTimeImmutable('2026-06-15 00:00:00');
            // Tuesday 16th: DOM=16 ≠ 15, DOW=2 ≠ Sunday → should NOT match
            $tuesday16 = new DateTimeImmutable('2026-06-16 00:00:00');

            expect(CronExpression::matches('0 0 15 * 0', $sunday14))->toBeTrue()
                ->and(CronExpression::matches('0 0 15 * 0', $monday15))->toBeTrue()
                ->and(CronExpression::matches('0 0 15 * 0', $tuesday16))->toBeFalse();
        },
    );

    it('steps a star-slash field from the start of the field range', function (): void {
        $time0 = new DateTimeImmutable('2026-06-15 14:00:00');
        $time5 = new DateTimeImmutable('2026-06-15 14:05:00');
        $time10 = new DateTimeImmutable('2026-06-15 14:10:00');
        $time3 = new DateTimeImmutable('2026-06-15 14:03:00');

        expect(CronExpression::matches('*/5 * * * *', $time0))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time5))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time10))->toBeTrue()
            ->and(CronExpression::matches('*/5 * * * *', $time3))->toBeFalse();
    });

    it(
        'steps a non-zero-based range from the range start so 10-20/5 matches 10, 15, 20 but not 12',
        function (): void {
            $time10 = new DateTimeImmutable('2026-06-15 14:10:00');
            $time15 = new DateTimeImmutable('2026-06-15 14:15:00');
            $time20 = new DateTimeImmutable('2026-06-15 14:20:00');
            $time12 = new DateTimeImmutable('2026-06-15 14:12:00');
            $time5 = new DateTimeImmutable('2026-06-15 14:05:00');

            expect(CronExpression::matches('10-20/5 * * * *', $time10))->toBeTrue()
                ->and(CronExpression::matches('10-20/5 * * * *', $time15))->toBeTrue()
                ->and(CronExpression::matches('10-20/5 * * * *', $time20))->toBeTrue()
                ->and(CronExpression::matches('10-20/5 * * * *', $time12))->toBeFalse()
                ->and(CronExpression::matches('10-20/5 * * * *', $time5))->toBeFalse();
        },
    );

    it('matches even values for a stepped range field like 0-58/2', function (): void {
        $time0 = new DateTimeImmutable('2026-06-15 14:00:00');
        $time2 = new DateTimeImmutable('2026-06-15 14:02:00');
        $time58 = new DateTimeImmutable('2026-06-15 14:58:00');
        $time1 = new DateTimeImmutable('2026-06-15 14:01:00');
        $time3 = new DateTimeImmutable('2026-06-15 14:03:00');

        expect(CronExpression::matches('0-58/2 * * * *', $time0))->toBeTrue()
            ->and(CronExpression::matches('0-58/2 * * * *', $time2))->toBeTrue()
            ->and(CronExpression::matches('0-58/2 * * * *', $time58))->toBeTrue()
            ->and(CronExpression::matches('0-58/2 * * * *', $time1))->toBeFalse()
            ->and(CronExpression::matches('0-58/2 * * * *', $time3))->toBeFalse();
    });

    it('matches every minute expression', function (): void {
        $time1 = new DateTimeImmutable('2026-06-15 14:30:00');
        $time2 = new DateTimeImmutable('2026-01-01 00:00:00');
        $time3 = new DateTimeImmutable('2026-12-31 23:59:00');

        expect(CronExpression::matches('* * * * *', $time1))->toBeTrue()
            ->and(CronExpression::matches('* * * * *', $time2))->toBeTrue()
            ->and(CronExpression::matches('* * * * *', $time3))->toBeTrue();
    });

    it('matches hourly at top of hour', function (): void {
        $timeTop = new DateTimeImmutable('2026-06-15 14:00:00');
        $timeNotTop = new DateTimeImmutable('2026-06-15 14:30:00');

        expect(CronExpression::matches('0 * * * *', $timeTop))->toBeTrue()
            ->and(CronExpression::matches('0 * * * *', $timeNotTop))->toBeFalse();
    });

    it('matches daily at midnight', function (): void {
        $midnight = new DateTimeImmutable('2026-06-15 00:00:00');
        $noon = new DateTimeImmutable('2026-06-15 12:00:00');

        expect(CronExpression::matches('0 0 * * *', $midnight))->toBeTrue()
            ->and(CronExpression::matches('0 0 * * *', $noon))->toBeFalse();
    });
});
