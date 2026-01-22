<?php

declare(strict_types=1);

use Marko\Queue\FailedJob;

describe('FailedJob', function (): void {
    it('stores all properties correctly', function (): void {
        $failedAt = new DateTimeImmutable('2024-01-15 10:30:00');

        $failedJob = new FailedJob(
            id: 'failed-123',
            queue: 'default',
            payload: 'serialized-job-data',
            exception: 'Exception: Something went wrong',
            failedAt: $failedAt,
        );

        expect($failedJob->id)->toBe('failed-123')
            ->and($failedJob->queue)->toBe('default')
            ->and($failedJob->payload)->toBe('serialized-job-data')
            ->and($failedJob->exception)->toBe('Exception: Something went wrong')
            ->and($failedJob->failedAt)->toBe($failedAt);
    });

    it('is readonly', function (): void {
        $reflection = new ReflectionClass(FailedJob::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});
