<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Http;

use Marko\Roadrunner\Exceptions\StreamingResponseException;
use Marko\Roadrunner\Http\Psr7ResponseBridge;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;
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

it('throws when handed a streaming response', function (): void {
    $stream = new SseStream(dataProvider: fn (): array => []);
    $response = new StreamingResponse($stream);
    $bridge = new Psr7ResponseBridge();

    expect(fn () => $bridge->bridge($response))
        ->toThrow(StreamingResponseException::class);
});

it('does not require the sse package to be installed', function (): void {
    $response = new Response(body: 'ok', statusCode: 200);
    $bridge = new Psr7ResponseBridge(streamingResponseClass: 'Marko\Sse\NotInstalledStreamingResponse');

    $psr7Response = $bridge->bridge($response);

    expect($psr7Response->getStatusCode())->toBe(200)
        ->and((string) $psr7Response->getBody())->toBe('ok');
});
