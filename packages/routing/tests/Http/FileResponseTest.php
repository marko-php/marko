<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\FileResponseException;
use Marko\Routing\Exceptions\RangeNotSatisfiableException;
use Marko\Routing\File\FileSourceInterface;
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\Http\ConditionalRequest;
use Marko\Routing\Http\ContentDisposition;
use Marko\Routing\Http\FileOffload;
use Marko\Routing\Http\FileResponse;
use Marko\Routing\Http\FileResponseFactory;
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Request;
use Marko\Routing\RoutingConfig;
use Marko\Routing\Tests\Http\Support\StreamCapture;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @param array<string, string> $headers e.g. ['Range' => 'bytes=0-9']
 */
function fileRequest(
    array $headers = [],
    string $method = 'GET',
): Request {
    $server = ['REQUEST_METHOD' => $method];

    foreach ($headers as $name => $value) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return new Request(server: $server);
}

describe('FileResponse', function (): void {
    beforeEach(function (): void {
        $this->contents = implode('', array_map(fn (int $i): string => chr(65 + $i % 26), range(0, 999)));
        $this->path = tempnam(sys_get_temp_dir(), 'file_response_') . '.txt';
        file_put_contents($this->path, $this->contents);
        touch($this->path, 1_700_000_000);
        $this->source = new LocalFileSource($this->path);
        $this->etag = ConditionalRequest::etagFor(1000, 1_700_000_000);
    });

    afterEach(function (): void {
        @unlink($this->path);
    });

    it('serves the full file with 200, length, type, validators and Accept-Ranges', function (): void {
        $response = FileResponse::fromSource(fileRequest(), $this->source);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('')
            ->and(StreamCapture::body($response))->toBe($this->contents)
            ->and($response->headers())->toBe([
                'Content-Type' => 'text/plain',
                'Last-Modified' => 'Tue, 14 Nov 2023 22:13:20 GMT',
                'ETag' => $this->etag,
                'Accept-Ranges' => 'bytes',
                'Content-Length' => '1000',
            ]);
    });

    it('serves a range with 206 and Content-Range', function (string $range, int $start, int $length): void {
        $response = FileResponse::fromSource(fileRequest(['Range' => $range]), $this->source);
        $end = $start + $length - 1;

        expect($response->statusCode())->toBe(206)
            ->and(StreamCapture::body($response))->toBe(substr($this->contents, $start, $length))
            ->and($response->headers()['Content-Range'])->toBe("bytes $start-$end/1000")
            ->and($response->headers()['Content-Length'])->toBe((string) $length)
            ->and($response->byteRange()?->start())->toBe($start);
    })->with([
        'first' => ['bytes=0-99', 0, 100],
        'middle' => ['bytes=400-649', 400, 250],
        'last' => ['bytes=900-999', 900, 100],
        'open-ended' => ['bytes=990-', 990, 10],
        'suffix' => ['bytes=-500', 500, 500],
    ]);

    it('throws a 416 with Content-Range: bytes */size for an invalid range', function (): void {
        try {
            FileResponse::fromSource(fileRequest(['Range' => 'bytes=5000-6000']), $this->source);
            $this->fail('Expected RangeNotSatisfiableException');
        } catch (RangeNotSatisfiableException $exception) {
            expect($exception->getStatusCode())->toBe(416)
                ->and($exception->getHeaders())->toBe(['Content-Range' => 'bytes */1000']);
        }
    });

    it('answers a multi-range request with the full 200 body', function (): void {
        $response = FileResponse::fromSource(fileRequest(['Range' => 'bytes=0-9,20-29']), $this->source);

        expect($response->statusCode())->toBe(200)
            ->and(StreamCapture::body($response))->toBe($this->contents);
    });

    it('ignores Range on methods other than GET', function (): void {
        $response = FileResponse::fromSource(fileRequest(['Range' => 'bytes=0-9'], 'POST'), $this->source);

        expect($response->statusCode())->toBe(200);
    });

    it('answers If-None-Match with 304 and no body', function (): void {
        $response = FileResponse::fromSource(fileRequest(['If-None-Match' => $this->etag]), $this->source);

        expect($response->statusCode())->toBe(304)
            ->and(StreamCapture::body($response))->toBe('')
            ->and($response->headers())->toBe([
                'ETag' => $this->etag,
                'Last-Modified' => 'Tue, 14 Nov 2023 22:13:20 GMT',
            ]);
    });

    it('answers If-Modified-Since with 304 when the file has not changed', function (): void {
        $response = FileResponse::fromSource(
            fileRequest(['If-Modified-Since' => 'Tue, 14 Nov 2023 22:13:20 GMT']),
            $this->source,
        );

        expect($response->statusCode())->toBe(304);
    });

    it('serves the file when If-Modified-Since is older than the file', function (): void {
        $response = FileResponse::fromSource(
            fileRequest(['If-Modified-Since' => 'Tue, 14 Nov 2023 22:13:19 GMT']),
            $this->source,
        );

        expect($response->statusCode())->toBe(200);
    });

    it('answers a Range whose If-Range no longer matches with the full 200 body', function (): void {
        $response = FileResponse::fromSource(
            fileRequest(['Range' => 'bytes=0-9', 'If-Range' => 'Mon, 13 Nov 2023 00:00:00 GMT']),
            $this->source,
        );

        expect($response->statusCode())->toBe(200)
            ->and(StreamCapture::body($response))->toBe($this->contents);
    });

    it('honours a Range whose If-Range date still matches', function (): void {
        $response = FileResponse::fromSource(
            fileRequest(['Range' => 'bytes=0-9', 'If-Range' => 'Tue, 14 Nov 2023 22:13:20 GMT']),
            $this->source,
        );

        expect($response->statusCode())->toBe(206)
            ->and(StreamCapture::body($response))->toBe(substr($this->contents, 0, 10));
    });

    it('sets Content-Disposition with a non-ASCII attachment filename', function (): void {
        $response = FileResponse::fromSource(fileRequest(), $this->source)
            ->withDisposition(ContentDisposition::attachment('vidéo finale.mp4'));

        expect($response->headers()['Content-Disposition'])
            ->toBe("attachment; filename=\"vid_o finale.mp4\"; filename*=UTF-8''vid%C3%A9o%20finale.mp4")
            ->and(StreamCapture::body($response))->toBe($this->contents);
    });

    it('lets the Content-Type be overridden', function (): void {
        $response = FileResponse::fromSource(fileRequest(), $this->source)
            ->withHeader('Content-Type', 'video/mp4');

        expect($response->headers()['Content-Type'])->toBe('video/mp4');
    });

    it('sends headers only for HEAD', function (): void {
        $response = FileResponse::fromSource(fileRequest(method: 'HEAD'), $this->source)->withoutBody();

        expect(StreamCapture::send($response))->toBe('')
            ->and($response->headers()['Content-Length'])->toBe('1000');
    });

    it('stops streaming when the client disconnects', function (): void {
        $response = FileResponse::fromSource(fileRequest(), $this->source)
            ->withOutputStreamer(StreamCapture::streamer(aborted: true));

        ob_start();
        $response->sendBody();
        $output = ob_get_clean();

        expect($output)->toBe('');
    });

    it('streams from any FileSourceInterface', function (): void {
        $source = new class () implements FileSourceInterface
        {
            public function size(): int
            {
                return 6;
            }

            public function lastModified(): int
            {
                return 0;
            }

            public function mimeType(): string
            {
                return 'application/x-custom';
            }

            public function filename(): string
            {
                return 'custom.bin';
            }

            public function stream(
                int $offset = 0,
                ?int $length = null,
            ): iterable {
                yield substr('abcdef', $offset, $length);
            }
        };

        $response = FileResponse::fromSource(fileRequest(['Range' => 'bytes=2-3']), $source);

        expect(StreamCapture::body($response))->toBe('cd')
            ->and($response->fileSource())->toBe($source);
    });

    it('keeps memory flat while serving a large file', function (): void {
        $largePath = tempnam(sys_get_temp_dir(), 'file_response_large_');
        $handle = fopen($largePath, 'wb');
        $block = str_repeat('x', 1024 * 1024);

        for ($i = 0; $i < 64; $i++) {
            fwrite($handle, $block);
        }

        fclose($handle);
        unset($block);

        $counter = new class () extends OutputStreamer
        {
            public int $bytes = 0;

            public function prepare(): void {}

            public function write(
                string $chunk,
            ): bool {
                $this->bytes += strlen($chunk);

                return true;
            }
        };

        try {
            $response = FileResponse::fromSource(fileRequest(), new LocalFileSource($largePath))
                ->withOutputStreamer($counter);

            memory_reset_peak_usage();
            $baseline = memory_get_usage();
            $response->sendBody();
            $peakGrowth = memory_get_peak_usage() - $baseline;
        } finally {
            unlink($largePath);
        }

        expect($counter->bytes)->toBe(64 * 1024 * 1024)
            ->and($peakGrowth)->toBeLessThan(2 * 1024 * 1024);
    });

    describe('file offload', function (): void {
        it('sends the offload header and no body when configured', function (): void {
            $offload = new FileOffload(header: 'X-Sendfile');
            $response = FileResponse::fromSource(fileRequest(['Range' => 'bytes=0-9']), $this->source, $offload);

            expect($response->statusCode())->toBe(200)
                ->and($response->headers()['X-Sendfile'])->toBe($this->path)
                ->and($response->headers())->not->toHaveKey('Content-Length')
                ->and(StreamCapture::body($response))->toBe('');
        });

        it('maps the path through the prefix map for X-Accel-Redirect', function (): void {
            $directory = dirname($this->path) . '/';
            $offload = new FileOffload(header: 'X-Accel-Redirect', prefixMap: [
                '/elsewhere/' => '/nope/',
                $directory => '/protected/',
            ]);
            $response = FileResponse::fromSource(fileRequest(), $this->source, $offload);

            expect($response->headers()['X-Accel-Redirect'])->toBe('/protected/' . basename($this->path));
        });

        it('sends no offload header when not configured', function (): void {
            $response = FileResponse::fromSource(fileRequest(), $this->source);

            expect($response->headers())->not->toHaveKey('X-Sendfile')
                ->and($response->headers())->not->toHaveKey('X-Accel-Redirect');
        });

        it('applies the configured offload through FileResponseFactory', function (): void {
            $factory = new FileResponseFactory(new RoutingConfig(new FakeConfigRepository([
                'routing.file_offload.header' => 'X-Sendfile',
                'routing.file_offload.prefix_map' => [],
            ])));

            expect($factory->fromSource(fileRequest(), $this->source)->headers()['X-Sendfile'])->toBe($this->path);
        });

        it('streams from PHP through FileResponseFactory when the offload header is empty', function (): void {
            $factory = new FileResponseFactory(new RoutingConfig(new FakeConfigRepository([
                'routing.file_offload.header' => '',
            ])));
            $response = $factory->fromSource(fileRequest(), $this->source);

            expect($response->headers())->not->toHaveKey('X-Sendfile')
                ->and(StreamCapture::body($response))->toBe($this->contents);
        });

        it('rejects an unsupported header and X-Accel-Redirect without a prefix map', function (): void {
            expect(fn () => new FileOffload(header: 'X-Whatever'))->toThrow(FileResponseException::class)
                ->and(fn () => new FileOffload(header: 'X-Accel-Redirect'))->toThrow(FileResponseException::class);
        });

        it('throws when the prefix map does not cover the file', function (): void {
            $offload = new FileOffload(header: 'X-Accel-Redirect', prefixMap: ['/elsewhere/' => '/nope/']);

            expect(fn () => FileResponse::fromSource(fileRequest(), $this->source, $offload))
                ->toThrow(FileResponseException::class);
        });
    });
});
