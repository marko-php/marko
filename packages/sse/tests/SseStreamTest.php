<?php

declare(strict_types=1);

use Marko\Sse\SseEvent;
use Marko\Sse\SseStream;

describe('SseStream', function (): void {
    it('yields formatted events from data provider', function (): void {
        $stream = new SseStream(
            dataProvider: fn(): array => [new SseEvent(data: 'hello', event: 'message', id: '1')],
            timeout: 0,
        );

        $chunks = iterator_to_array($stream);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0])->toContain("data: hello\n");
    });

    it('yields heartbeat comment when no events and heartbeat interval elapsed', function (): void {
        $stream = new SseStream(
            dataProvider: fn(): array => [],
            heartbeatInterval: 0,
            timeout: 0,
        );

        $chunks = iterator_to_array($stream);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0])->toBe(": keepalive\n\n");
    });

    it('stops iteration after timeout', function (): void {
        $callCount = 0;
        $stream = new SseStream(
            dataProvider: function () use (&$callCount): array {
                $callCount++;
                return [new SseEvent(data: "event-{$callCount}")];
            },
            timeout: 0,
        );

        $chunks = iterator_to_array($stream);

        // With timeout: 0, only 1 tick runs
        expect($chunks)->toHaveCount(1);
    });

    it('yields events from multiple data provider calls across ticks', function (): void {
        $callCount = 0;
        $stream = new SseStream(
            dataProvider: function () use (&$callCount): array {
                $callCount++;
                return $callCount <= 2
                    ? [new SseEvent(data: "event-{$callCount}")]
                    : [];
            },
            timeout: 1,
            pollInterval: 0,
        );

        $chunks = array_values(array_filter(
            iterator_to_array($stream),
            fn(string $chunk): bool => str_starts_with($chunk, 'data:'),
        ));

        expect(count($chunks))->toBeGreaterThanOrEqual(2);
    });

    it('does not yield heartbeat when events were sent within interval', function (): void {
        $stream = new SseStream(
            dataProvider: fn(): array => [new SseEvent(data: 'test')],
            heartbeatInterval: 60,  // 60s interval - won't trigger
            timeout: 0,
        );

        $chunks = iterator_to_array($stream);

        $heartbeats = array_filter(
            $chunks,
            fn(string $chunk): bool => str_starts_with($chunk, ': keepalive'),
        );

        expect($heartbeats)->toBeEmpty();
    });

    it('yields no output when data provider returns empty and heartbeat not due', function (): void {
        $stream = new SseStream(
            dataProvider: fn(): array => [],
            heartbeatInterval: 60,  // 60s - won't trigger immediately
            timeout: 0,
        );

        $chunks = iterator_to_array($stream);

        expect($chunks)->toBeEmpty();
    });
});
