<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\RangeNotSatisfiableException;
use Marko\Routing\Http\ByteRange;

describe('ByteRange::fromHeader()', function (): void {
    it('returns null without a Range header', function (): void {
        expect(ByteRange::fromHeader(null, 1000))->toBeNull()
            ->and(ByteRange::fromHeader('', 1000))->toBeNull();
    });

    it('parses a closed range', function (): void {
        $range = ByteRange::fromHeader('bytes=100-199', 1000);

        expect($range->start())->toBe(100)
            ->and($range->end())->toBe(199)
            ->and($range->length())->toBe(100)
            ->and($range->contentRange(1000))->toBe('bytes 100-199/1000');
    });

    it('parses an open-ended range to the end of the file', function (): void {
        $range = ByteRange::fromHeader('bytes=900-', 1000);

        expect($range->start())->toBe(900)
            ->and($range->end())->toBe(999);
    });

    it('clamps an end past the file to the last byte', function (): void {
        $range = ByteRange::fromHeader('bytes=0-5000', 1000);

        expect($range->end())->toBe(999)
            ->and($range->length())->toBe(1000);
    });

    it('parses a suffix range as the last bytes of the file', function (): void {
        $range = ByteRange::fromHeader('bytes=-500', 1000);

        expect($range->start())->toBe(500)
            ->and($range->end())->toBe(999)
            ->and($range->length())->toBe(500);
    });

    it('serves the whole file for a suffix longer than the file', function (): void {
        $range = ByteRange::fromHeader('bytes=-5000', 1000);

        expect($range->start())->toBe(0)
            ->and($range->end())->toBe(999);
    });

    it('ignores a multi-range request so the whole file is served', function (): void {
        expect(ByteRange::fromHeader('bytes=0-99,200-299', 1000))->toBeNull();
    });

    it('ignores a unit other than bytes', function (): void {
        expect(ByteRange::fromHeader('items=0-5', 1000))->toBeNull();
    });

    it('throws a 416 carrying the size for an invalid range', function (string $header): void {
        try {
            ByteRange::fromHeader($header, 1000);
            $this->fail('Expected RangeNotSatisfiableException');
        } catch (RangeNotSatisfiableException $exception) {
            expect($exception->getStatusCode())->toBe(416)
                ->and($exception->getHeaders())->toBe(['Content-Range' => 'bytes */1000']);
        }
    })->with([
        'start past the end' => 'bytes=1000-1100',
        'start after end' => 'bytes=500-100',
        'empty suffix' => 'bytes=-0',
        'no numbers' => 'bytes=-',
        'garbage' => 'bytes=abc-def',
    ]);

    it('throws for any range on an empty file', function (): void {
        expect(fn () => ByteRange::fromHeader('bytes=-10', 0))->toThrow(RangeNotSatisfiableException::class)
            ->and(fn () => ByteRange::fromHeader('bytes=0-', 0))->toThrow(RangeNotSatisfiableException::class);
    });
});
