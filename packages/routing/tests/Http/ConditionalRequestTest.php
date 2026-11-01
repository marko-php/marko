<?php

declare(strict_types=1);

use Marko\Routing\Http\ConditionalRequest;
use Marko\Routing\Http\Request;

describe('ConditionalRequest', function (): void {
    $mtime = 1_700_000_000;
    $etag = ConditionalRequest::etagFor(1234, $mtime);

    it('builds a weak etag from size and mtime', function () use ($etag): void {
        expect($etag)->toBe('W/"4d2-6553f100"');
    });

    it('formats an IMF-fixdate', function () use ($mtime): void {
        expect(ConditionalRequest::httpDate($mtime))->toBe('Tue, 14 Nov 2023 22:13:20 GMT');
    });

    it('reads the validator headers from the request', function () use ($etag, $mtime): void {
        $request = new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]);

        expect(ConditionalRequest::fromRequest($request)->isNotModified($etag, $mtime))->toBeTrue();
    });

    it('is not modified when If-None-Match lists the etag, weak or strong', function () use ($etag, $mtime): void {
        expect(new ConditionalRequest(ifNoneMatch: '"other", ' . $etag)->isNotModified($etag, $mtime))->toBeTrue()
            ->and(new ConditionalRequest(ifNoneMatch: '"4d2-6553f100"')->isNotModified($etag, $mtime))->toBeTrue()
            ->and(new ConditionalRequest(ifNoneMatch: '*')->isNotModified($etag, $mtime))->toBeTrue();
    });

    it(
        'is modified when If-None-Match does not list the etag, whatever If-Modified-Since says',
        function () use ($etag, $mtime): void {
            $conditional = new ConditionalRequest(
                ifNoneMatch: '"stale"',
                ifModifiedSince: ConditionalRequest::httpDate($mtime + 60),
            );

            expect($conditional->isNotModified($etag, $mtime))->toBeFalse();
        },
    );

    it('compares If-Modified-Since with the modification time', function () use ($etag, $mtime): void {
        $current = new ConditionalRequest(ifModifiedSince: ConditionalRequest::httpDate($mtime));
        $stale = new ConditionalRequest(ifModifiedSince: ConditionalRequest::httpDate($mtime - 1));

        expect($current->isNotModified($etag, $mtime))->toBeTrue()
            ->and($stale->isNotModified($etag, $mtime))->toBeFalse();
    });

    it('ignores an unparseable If-Modified-Since date', function () use ($etag, $mtime): void {
        expect(new ConditionalRequest(ifModifiedSince: 'not a date')->isNotModified($etag, $mtime))->toBeFalse();
    });

    it('is modified with no validators at all', function () use ($etag, $mtime): void {
        expect(new ConditionalRequest()->isNotModified($etag, $mtime))->toBeFalse();
    });

    it('allows a range without If-Range or when the If-Range date matches', function () use ($etag, $mtime): void {
        expect(new ConditionalRequest()->allowsRange($etag, $mtime))->toBeTrue()
            ->and(
                new ConditionalRequest(ifRange: ConditionalRequest::httpDate($mtime))->allowsRange($etag, $mtime),
            )->toBeTrue();
    });

    it('refuses a range when the If-Range date does not match', function () use ($etag, $mtime): void {
        expect(
            new ConditionalRequest(ifRange: ConditionalRequest::httpDate($mtime - 10))->allowsRange($etag, $mtime),
        )->toBeFalse();
    });

    it(
        'refuses a range for an If-Range etag, which needs a strong match a weak etag never gives',
        function () use ($etag, $mtime): void {
            expect(new ConditionalRequest(ifRange: $etag)->allowsRange($etag, $mtime))->toBeFalse()
                ->and(new ConditionalRequest(ifRange: '"abc"')->allowsRange('"abc"', $mtime))->toBeTrue()
                ->and(new ConditionalRequest(ifRange: '"abc"')->allowsRange('"xyz"', $mtime))->toBeFalse();
        },
    );
});
