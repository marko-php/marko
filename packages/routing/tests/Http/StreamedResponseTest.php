<?php

declare(strict_types=1);

use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponse;
use Marko\Routing\Http\StreamedResponseInterface;
use Marko\Routing\Tests\Http\Support\StreamCapture;

describe('Response::send() with a streamed response', function (): void {
    it('calls sendBody() instead of echoing body()', function (): void {
        $response = new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('first,');
            $outputStreamer->write('second');
        });

        expect($response->body())->toBe('')
            ->and(StreamCapture::send($response))->toBe('first,second');
    });

    it('skips sendBody() when the body was omitted for HEAD', function (): void {
        $called = false;
        $response = new StreamedResponse(function () use (&$called): void {
            $called = true;
        })->withoutBody();

        expect(StreamCapture::send($response))->toBe('')
            ->and($called)->toBeFalse();
    });

    it('still echoes body() for a plain response', function (): void {
        expect(StreamCapture::send(new Response(body: 'plain')))->toBe('plain');
    });

    it('lets a third-party implementer of the interface stream through Response::send()', function (): void {
        $response = new class () extends Response implements StreamedResponseInterface
        {
            public function sendBody(): void
            {
                echo 'custom stream';
            }

            public function withOutputStreamer(OutputStreamer $outputStreamer): static
            {
                return $this;
            }
        };

        expect(StreamCapture::send($response))->toBe('custom stream');
    });
});

describe('StreamedResponse', function (): void {
    it('passes the output streamer to the callback and keeps status and headers', function (): void {
        $response = new StreamedResponse(
            callback: function (OutputStreamer $outputStreamer): void {
                foreach (['id,name', "\n1,Ada"] as $row) {
                    $outputStreamer->write($row);
                }
            },
            statusCode: 201,
            headers: ['Content-Type' => 'text/csv'],
        );

        expect(StreamCapture::body($response))->toBe("id,name\n1,Ada")
            ->and($response->statusCode())->toBe(201)
            ->and($response->headers())->toBe(['Content-Type' => 'text/csv']);
    });

    it('keeps the callback when decorated', function (): void {
        $response = new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('still here');
        })->withHeader('X-Export', 'yes')->withStatus(202);

        expect($response)->toBeInstanceOf(StreamedResponse::class)
            ->and(StreamCapture::body($response))->toBe('still here')
            ->and($response->headers())->toBe(['X-Export' => 'yes']);
    });

    it('returns a copy from withOutputStreamer() without changing the original', function (): void {
        $original = new StreamedResponse(fn (): null => null);

        expect($original->withOutputStreamer(StreamCapture::streamer()))->not->toBe($original);
    });
});

describe('OutputStreamer', function (): void {
    it('writes and reports success while the client is connected', function (): void {
        $outputStreamer = StreamCapture::streamer();

        ob_start();
        $written = $outputStreamer->write('chunk');
        $output = ob_get_clean();

        expect($written)->toBeTrue()
            ->and($output)->toBe('chunk');
    });

    it('stops writing once the client has disconnected', function (): void {
        $outputStreamer = StreamCapture::streamer(aborted: true);

        ob_start();
        $written = $outputStreamer->write('chunk');
        $output = ob_get_clean();

        expect($written)->toBeFalse()
            ->and($output)->toBe('');
    });
});
