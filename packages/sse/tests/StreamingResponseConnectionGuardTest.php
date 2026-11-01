<?php

declare(strict_types=1);

use Marko\Routing\Http\OutputStreamer;
use Marko\Sse\SseConnectionLimiter;
use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;
use Marko\Sse\Tests\Support\RecordingCache;

/**
 * StreamingResponse whose OutputStreamer leaves the output buffers alone and reports the given
 * abort state, so send() can run inside the test process.
 */
function guardedStreamingResponse(
    SseStream $stream,
    ?SseConnectionLimiter $connectionLimiter,
    bool $aborted = false,
): StreamingResponse {
    $outputStreamer = new class ($aborted) extends OutputStreamer
    {
        public function __construct(
            private readonly bool $aborted,
        ) {}

        public function prepare(): void {}

        public function connectionAborted(): bool
        {
            return $this->aborted;
        }
    };

    return new StreamingResponse(stream: $stream, connectionLimiter: $connectionLimiter)
        ->withOutputStreamer($outputStreamer);
}

function captureSend(StreamingResponse $response): string
{
    ob_start();

    try {
        $response->send();
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

function singleEventStream(?Closure $onPoll = null): SseStream
{
    return new SseStream(
        dataProvider: function () use ($onPoll): array {
            if ($onPoll !== null) {
                $onPoll();
            }

            return [new SseEvent(data: 'payload')];
        },
        timeout: 0,
        pollInterval: 0,
    );
}

describe('StreamingResponse connection guard', function (): void {
    it('streams without touching the cache when no limiter is given', function (): void {
        expect(captureSend(guardedStreamingResponse(singleEventStream(), null)))->toContain('data: payload');
    });

    it('streams without touching the cache when the limiter is disabled', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: null, retryAfter: 5);

        expect(captureSend(guardedStreamingResponse(singleEventStream(), $limiter)))->toContain('data: payload')
            ->and($cache->increments)->toBeEmpty()
            ->and($cache->deletes)->toBeEmpty();
    });

    it('takes a slot sized to the stream timeout and releases it after streaming', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);

        $output = captureSend(guardedStreamingResponse(singleEventStream(), $limiter));

        expect($output)->toContain('data: payload')
            ->and($cache->increments[0]['ttl'])->toBe(StreamingResponse::SLOT_TTL_GRACE_SECONDS)
            ->and($cache->deletes)->toBe(['sse.connection_slot.0'])
            ->and($cache->values)->toBeEmpty();
    });

    it('releases its slot when the stream throws', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);
        $stream = singleEventStream(fn () => throw new RuntimeException('data source failed'));

        expect(fn () => captureSend(guardedStreamingResponse($stream, $limiter)))
            ->toThrow(RuntimeException::class, 'data source failed')
            ->and($cache->deletes)->toBe(['sse.connection_slot.0']);
    });

    it('releases its slot when the client aborts', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);

        $output = captureSend(guardedStreamingResponse(singleEventStream(), $limiter, aborted: true));

        expect($output)->not->toContain('data: payload')
            ->and($cache->deletes)->toBe(['sse.connection_slot.0']);
    });

    it('does not stream or release when no slot is free', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);
        $limiter->acquire(ttl: 60);
        $polled = false;

        $output = captureSend(guardedStreamingResponse(singleEventStream(function () use (&$polled): void {
            $polled = true;
        }), $limiter));

        expect($output)->toBe('')
            ->and($polled)->toBeFalse()
            ->and($cache->deletes)->toBeEmpty();
    });

    it('acquires no connection slot and does not stream when the body was omitted', function (): void {
        $cache = new RecordingCache();
        $limiter = new SseConnectionLimiter(cache: $cache, maxConnections: 1, retryAfter: 5);
        $polled = false;
        $response = guardedStreamingResponse(singleEventStream(function () use (&$polled): void {
            $polled = true;
        }), $limiter)->withoutBody();

        expect(captureSend($response))->toBe('')
            ->and($polled)->toBeFalse()
            ->and($cache->increments)->toBeEmpty()
            ->and($cache->deletes)->toBeEmpty();
    });

    it('responds 503 with Retry-After when no slot is free', function (): void {
        $limiter = new SseConnectionLimiter(cache: new RecordingCache(), maxConnections: 1, retryAfter: 7);
        $response = new StreamingResponse(stream: singleEventStream(), connectionLimiter: $limiter);

        $unavailable = $response->serviceUnavailableResponse();

        expect($unavailable->statusCode())->toBe(503)
            ->and($unavailable->headers())->toBe(['Retry-After' => '7', 'Cache-Control' => 'no-cache'])
            ->and($unavailable->body())->toBe('');
    });
});
