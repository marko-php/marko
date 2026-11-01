<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Http;

use Marko\Roadrunner\Exceptions\StreamingResponseException;
use Marko\Roadrunner\Http\Psr7ResponseBridge;
use Marko\Routing\File\LocalFileSource;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\FileResponse;
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponse;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

it('maps the status code to the psr7 response', function (): void {
    $response = new Response(body: '', statusCode: 201);
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getStatusCode())->toBe(201);
});

it('maps the body to the psr7 response', function (): void {
    $response = new Response(body: 'hello world', statusCode: 200);
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect((string) $psr7Response->getBody())->toBe('hello world');
});

it('maps regular headers to the psr7 response', function (): void {
    $response = new Response(
        body: '',
        statusCode: 200,
        headers: ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc123'],
    );
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($psr7Response->getHeaderLine('X-Request-Id'))->toBe('abc123');
});

it('emits a distinct set cookie header for each cookie on the response', function (): void {
    $cookie = new Cookie(name: 'session', value: 'abc123');
    $response = (new Response(body: '', statusCode: 200))->withCookie($cookie);
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getHeader('Set-Cookie'))->toBe([$cookie->toSetCookieString()]);
});

it('preserves multiple cookies rather than collapsing them', function (): void {
    $sessionCookie = new Cookie(name: 'session', value: 'abc123');
    $preferencesCookie = new Cookie(name: 'preferences', value: 'dark-mode');
    $response = (new Response(body: '', statusCode: 200))
        ->withCookie($sessionCookie)
        ->withCookie($preferencesCookie);
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getHeader('Set-Cookie'))->toBe([
        $sessionCookie->toSetCookieString(),
        $preferencesCookie->toSetCookieString(),
    ]);
});

it('preserves a header value containing a colon', function (): void {
    $response = new Response(
        body: '',
        statusCode: 302,
        headers: ['Location' => 'https://example.test/path'],
    );
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getHeaderLine('Location'))->toBe('https://example.test/path');
});

it('rejects a server-sent events response with a specific message', function (): void {
    $stream = new SseStream(dataProvider: fn (): array => []);
    $response = new StreamingResponse($stream);
    $bridge = new Psr7ResponseBridge();

    expect(fn () => $bridge->bridge($response))
        ->toThrow(StreamingResponseException::class, 'server-sent events');
});

it('rejects any response declaring a text/event-stream content type', function (): void {
    $response = new StreamedResponse(
        function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write("data: hello\n\n");
        },
        headers: ['content-type' => 'text/event-stream; charset=utf-8'],
    );
    $bridge = new Psr7ResponseBridge();

    expect(fn () => $bridge->bridge($response))
        ->toThrow(StreamingResponseException::class, StreamedResponse::class);
});

it('writes a streamed response body into the psr7 stream', function (): void {
    $response = new StreamedResponse(
        function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write("id,name\n");
            $outputStreamer->write("1,Ada\n");
        },
        statusCode: 201,
        headers: ['Content-Type' => 'text/csv'],
    );
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect((string) $psr7Response->getBody())->toBe("id,name\n1,Ada\n")
        ->and($psr7Response->getStatusCode())->toBe(201)
        ->and($psr7Response->getHeaderLine('Content-Type'))->toBe('text/csv');
});

it('never calls the streamed response callback when the body was omitted', function (): void {
    $called = false;
    $response = new StreamedResponse(function (OutputStreamer $outputStreamer) use (&$called): void {
        $called = true;
        $outputStreamer->write('body');
    })->withoutBody();
    $bridge = new Psr7ResponseBridge();

    $psr7Response = $bridge->bridge($response);

    expect($called)->toBeFalse()
        ->and((string) $psr7Response->getBody())->toBe('');
});

it('leaves output buffers alone while writing a streamed body', function (): void {
    $response = new StreamedResponse(function (OutputStreamer $outputStreamer): void {
        $outputStreamer->write('chunk');
    });
    $bridge = new Psr7ResponseBridge();
    $level = ob_get_level();
    ob_start();

    try {
        $psr7Response = $bridge->bridge($response);
        $echoed = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    expect(ob_get_level())->toBe($level)
        ->and($echoed)->toBe('')
        ->and((string) $psr7Response->getBody())->toBe('chunk');
});

it('streams a whole file response into the psr7 body', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'rr_file_');
    file_put_contents($path, 'file contents');

    try {
        $response = FileResponse::fromSource(
            new Request(server: ['REQUEST_METHOD' => 'GET']),
            new LocalFileSource($path),
        );

        $psr7Response = new Psr7ResponseBridge()->bridge($response);

        expect($psr7Response->getStatusCode())->toBe(200)
            ->and((string) $psr7Response->getBody())->toBe('file contents')
            ->and($psr7Response->getHeaderLine('Content-Length'))->toBe('13')
            ->and($psr7Response->getHeaderLine('Accept-Ranges'))->toBe('bytes');
    } finally {
        unlink($path);
    }
});

it('streams only the requested range of a file response', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'rr_file_');
    file_put_contents($path, '0123456789');

    try {
        $response = FileResponse::fromSource(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_RANGE' => 'bytes=2-5']),
            new LocalFileSource($path),
        );

        $psr7Response = new Psr7ResponseBridge()->bridge($response);

        expect($psr7Response->getStatusCode())->toBe(206)
            ->and((string) $psr7Response->getBody())->toBe('2345')
            ->and($psr7Response->getHeaderLine('Content-Length'))->toBe('4')
            ->and($psr7Response->getHeaderLine('Content-Range'))->toBe('bytes 2-5/10');
    } finally {
        unlink($path);
    }
});

it('sends an empty body for a not modified file response', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'rr_file_');
    file_put_contents($path, 'contents');

    try {
        $source = new LocalFileSource($path);
        $etag = FileResponse::fromSource(new Request(server: ['REQUEST_METHOD' => 'GET']), $source)->headers()['ETag'];
        $response = FileResponse::fromSource(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'HTTP_IF_NONE_MATCH' => $etag]),
            $source,
        );

        $psr7Response = new Psr7ResponseBridge()->bridge($response);

        expect($psr7Response->getStatusCode())->toBe(304)
            ->and((string) $psr7Response->getBody())->toBe('');
    } finally {
        unlink($path);
    }
});

it('returns a rewound psr7 body for a streamed response', function (): void {
    $response = new StreamedResponse(function (OutputStreamer $outputStreamer): void {
        $outputStreamer->write('abc');
    });

    $body = new Psr7ResponseBridge()->bridge($response)->getBody();

    expect($body->tell())->toBe(0)
        ->and($body->getContents())->toBe('abc');
});
