<?php

declare(strict_types=1);

use Marko\Routing\Http\Response;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

describe('StreamingResponse', function (): void {
    it('extends Response', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response)->toBeInstanceOf(Response::class);
    });

    it('sets Content-Type header to text/event-stream', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->headers())->toHaveKey('Content-Type')
            ->and($response->headers()['Content-Type'])->toBe('text/event-stream');
    });

    it('sets Cache-Control header to no-cache', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->headers())->toHaveKey('Cache-Control')
            ->and($response->headers()['Cache-Control'])->toBe('no-cache');
    });

    it('sets Connection header to keep-alive', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->headers())->toHaveKey('Connection')
            ->and($response->headers()['Connection'])->toBe('keep-alive');
    });

    it('sets X-Accel-Buffering header to no', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->headers())->toHaveKey('X-Accel-Buffering')
            ->and($response->headers()['X-Accel-Buffering'])->toBe('no');
    });

    it('has 200 status code by default', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->statusCode())->toBe(200);
    });

    it('accepts custom status code', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
            statusCode: 201,
        );

        expect($response->statusCode())->toBe(201);
    });

    it('has empty body', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->body())->toBe('');
    });
});
