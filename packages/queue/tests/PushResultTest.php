<?php

declare(strict_types=1);

use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\PushResult;

describe('PushResult', function (): void {
    it('describes a queued job with its id', function (): void {
        $result = PushResult::pushed('job-1');

        expect($result->id)->toBe('job-1')
            ->and($result->pushed)->toBeTrue()
            ->and($result->skipReason)->toBeNull();
    });

    it('describes a skipped push with its reason and no id', function (): void {
        $result = PushResult::skipped('a job with the same unique id is already queued');

        expect($result->id)->toBeNull()
            ->and($result->pushed)->toBeFalse()
            ->and($result->skipReason)->toBe('a job with the same unique id is already queued');
    });

    it('is readonly', function (): void {
        expect(new ReflectionClass(PushResult::class)->isReadOnly())->toBeTrue();
    });

    it('rejects a pushed result without an id', function (): void {
        expect(fn () => new PushResult(id: null, pushed: true, skipReason: null))
            ->toThrow(QueueException::class, 'Invalid push result');
    });

    it('rejects a pushed result that also carries a skip reason', function (): void {
        expect(fn () => new PushResult(id: 'job-1', pushed: true, skipReason: 'duplicate'))
            ->toThrow(QueueException::class, 'Invalid push result');
    });

    it('rejects a skipped result without a reason', function (): void {
        expect(fn () => new PushResult(id: null, pushed: false, skipReason: null))
            ->toThrow(QueueException::class, 'Invalid push result');
    });

    it('rejects a skipped result that carries an id', function (): void {
        expect(fn () => new PushResult(id: 'job-1', pushed: false, skipReason: 'duplicate'))
            ->toThrow(QueueException::class, 'Invalid push result');
    });
});
