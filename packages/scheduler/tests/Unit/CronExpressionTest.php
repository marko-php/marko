<?php

declare(strict_types=1);

use Marko\Scheduler\CronExpression;

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
