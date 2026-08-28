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

    it('preserves the concrete subclass when decorating with a header', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        $decorated = $response->withHeader('X-Custom-Header', 'custom-value');

        expect($decorated)->toBeInstanceOf(StreamingResponse::class);
    });

    it('preserves the concrete subclass when decorating with a status', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        $decorated = $response->withStatus(500);

        expect($decorated)->toBeInstanceOf(StreamingResponse::class)
            ->and($decorated->statusCode())->toBe(500);
    });

    it('preserves subclass state such as the streaming payload when decorating', function (): void {
        $stream = new SseStream(dataProvider: fn (): array => []);
        $response = new StreamingResponse(stream: $stream);

        $decorated = $response->withHeader('X-Custom-Header', 'custom-value');

        $property = new ReflectionProperty(StreamingResponse::class, 'stream');

        expect($property->getValue($decorated))->toBe($stream);
    });

    it('still streams from a decorated streaming response', function (): void {
        // send() forcibly closes every output buffer level before it echoes
        // stream chunks, so ob_start()/ob_get_clean() cannot capture it in
        // this process. Run it in a real subprocess and capture actual
        // stdout instead.
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script = <<<PHP
            <?php
            require '$autoload';

            use Marko\Sse\SseEvent;
            use Marko\Sse\SseStream;
            use Marko\Sse\StreamingResponse;

            \$response = new StreamingResponse(
                stream: new SseStream(dataProvider: fn (): array => [new SseEvent(data: 'payload')], timeout: 0),
            );

            \$response->withHeader('X-Custom-Header', 'custom-value')->send();
            PHP;

        $scriptPath = tempnam(sys_get_temp_dir(), 'streaming_response_test_') . '.php';
        file_put_contents($scriptPath, $script);

        $process = proc_open(
            [PHP_BINARY, $scriptPath],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        unlink($scriptPath);

        expect($output)->toContain('data: payload');
    });

    it('does not clobber the sse headers when adding a header to a streaming response', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        $decorated = $response->withHeader('X-Custom-Header', 'custom-value');

        expect($decorated->headers())->toBe([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'X-Custom-Header' => 'custom-value',
        ]);
    });

    it('emits the same header lines for a streaming response subclass', function (): void {
        $response = new StreamingResponse(
            stream: new SseStream(dataProvider: fn (): array => []),
        );

        expect($response->headerLines())->toBe([
            'Content-Type: text/event-stream',
            'Cache-Control: no-cache',
            'Connection: keep-alive',
            'X-Accel-Buffering: no',
        ]);
    });
});
