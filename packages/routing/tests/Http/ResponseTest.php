<?php

declare(strict_types=1);

use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;

it('accepts status code, headers, and body', function (): void {
    $response = new Response(
        body: 'Hello World',
        statusCode: 201,
        headers: ['X-Custom-Header' => 'custom-value'],
    );

    expect($response->body())->toBe('Hello World')
        ->and($response->statusCode())->toBe(201)
        ->and($response->headers())->toBe(['X-Custom-Header' => 'custom-value']);
});

it('defaults to 200 status code', function (): void {
    $response = new Response(body: 'Hello');

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toBeEmpty();
});

it('creates JSON response with correct content-type', function (): void {
    $data = ['name' => 'John', 'age' => 30];
    $response = Response::json($data);

    expect($response->body())->toBe('{"name":"John","age":30}')
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($response->statusCode())->toBe(200);
});

it('creates JSON response with custom status code', function (): void {
    $data = ['error' => 'Not Found'];
    $response = Response::json($data, 404);

    expect($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('application/json');
});

it('creates HTML response with correct content-type', function (): void {
    $html = '<html><body>Hello World</body></html>';
    $response = Response::html($html);

    expect($response->body())->toBe($html)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
        ->and($response->statusCode())->toBe(200);
});

it('creates HTML response with custom status code', function (): void {
    $html = '<html><body>Not Found</body></html>';
    $response = Response::html($html, 404);

    expect($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');
});

it('creates redirect response with Location header', function (): void {
    $response = Response::redirect('/dashboard');

    expect($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/dashboard')
        ->and($response->statusCode())->toBe(302)
        ->and($response->body())->toBe('');
});

it('creates redirect response with custom status code', function (): void {
    $response = Response::redirect('/new-location', 301);

    expect($response->statusCode())->toBe(301)
        ->and($response->headers()['Location'])->toBe('/new-location');
});

it('returns a new instance from withHeader leaving the original unchanged', function (): void {
    $response = new Response(body: 'Hello');

    $decorated = $response->withHeader('X-Custom-Header', 'custom-value');

    expect($decorated)->not->toBe($response)
        ->and($response->headers())->toBeEmpty();
});

it('merges the new header into the existing headers', function (): void {
    $response = new Response(body: 'Hello', headers: ['X-Existing' => 'existing-value']);

    $decorated = $response->withHeader('X-Custom-Header', 'custom-value');

    expect($decorated->headers())->toBe([
        'X-Existing' => 'existing-value',
        'X-Custom-Header' => 'custom-value',
    ]);
});

it('merges a map of headers with withHeaders', function (): void {
    $response = new Response(body: 'Hello', headers: ['X-Existing' => 'existing-value']);

    $decorated = $response->withHeaders([
        'X-First' => 'first-value',
        'X-Second' => 'second-value',
    ]);

    expect($decorated->headers())->toBe([
        'X-Existing' => 'existing-value',
        'X-First' => 'first-value',
        'X-Second' => 'second-value',
    ]);
});

it('returns a new instance from withStatus leaving the original unchanged', function (): void {
    $response = new Response(body: 'Hello', statusCode: 200);

    $decorated = $response->withStatus(404);

    expect($decorated)->not->toBe($response)
        ->and($decorated->statusCode())->toBe(404)
        ->and($response->statusCode())->toBe(200);
});

it('returns a new instance from withCookie leaving the original unchanged', function (): void {
    $response = new Response(body: 'Hello');
    $cookie = new Cookie(name: 'session_id', value: 'abc123');

    $decorated = $response->withCookie($cookie);

    expect($decorated)->not->toBe($response)
        ->and($response->cookies())->toBeEmpty();
});

it('accumulates cookies that differ in name path or domain', function (): void {
    $response = new Response(body: 'Hello');
    $sessionCookie = new Cookie(name: 'session_id', value: 'abc123');
    $adminPathCookie = new Cookie(name: 'session_id', value: 'def456', path: '/admin');
    $apiDomainCookie = new Cookie(name: 'session_id', value: 'ghi789', domain: 'api.example.com');

    $decorated = $response
        ->withCookie($sessionCookie)
        ->withCookie($adminPathCookie)
        ->withCookie($apiDomainCookie);

    expect($decorated->cookies())->toBe([$sessionCookie, $adminPathCookie, $apiDomainCookie]);
});

it('replaces a cookie matching an existing name path and domain', function (): void {
    $response = new Response(body: 'Hello');
    $original = new Cookie(name: 'session_id', value: 'abc123', path: '/', domain: 'example.com');
    $replacement = new Cookie(name: 'session_id', value: 'xyz999', path: '/', domain: 'example.com');

    $decorated = $response
        ->withCookie($original)
        ->withCookie($replacement);

    expect($decorated->cookies())->toBe([$replacement]);
});

it('keeps cookies out of the headers collection', function (): void {
    $response = new Response(body: 'Hello', headers: ['X-Existing' => 'existing-value']);
    $cookie = new Cookie(name: 'session_id', value: 'abc123');

    $decorated = $response->withCookie($cookie);

    expect($decorated->headers())->toBe(['X-Existing' => 'existing-value']);
});

it('outputs headers and body when sent', function (): void {
    $response = new Response(
        body: 'Hello World',
        statusCode: 201,
        headers: ['X-Custom-Header' => 'custom-value'],
    );

    ob_start();
    $response->send();
    $output = ob_get_clean();

    expect($output)->toBe('Hello World');
});

it('returns regular headers as name colon value lines', function (): void {
    $response = new Response(
        body: 'Hello',
        headers: ['X-Custom-Header' => 'custom-value', 'Content-Type' => 'text/plain'],
    );

    expect($response->headerLines())->toBe([
        'X-Custom-Header: custom-value',
        'Content-Type: text/plain',
    ]);
});

it('returns a distinct set-cookie line for each cookie', function (): void {
    $response = new Response(body: 'Hello');
    $sessionCookie = new Cookie(name: 'session_id', value: 'abc123');
    $csrfCookie = new Cookie(name: 'csrf_token', value: 'def456');

    $decorated = $response
        ->withCookie($sessionCookie)
        ->withCookie($csrfCookie);

    expect($decorated->headerLines())->toBe([
        'Set-Cookie: ' . $sessionCookie->toSetCookieString(),
        'Set-Cookie: ' . $csrfCookie->toSetCookieString(),
    ]);
});

it('returns only regular header lines when the response has no cookies', function (): void {
    $response = new Response(body: 'Hello', headers: ['X-Custom-Header' => 'custom-value']);

    expect($response->headerLines())->toBe(['X-Custom-Header: custom-value']);
});

it('preserves cookie order in the emitted lines', function (): void {
    $response = new Response(body: 'Hello');
    $thirdCookie = new Cookie(name: 'third', value: '3');
    $firstCookie = new Cookie(name: 'first', value: '1');
    $secondCookie = new Cookie(name: 'second', value: '2');

    $decorated = $response
        ->withCookie($thirdCookie)
        ->withCookie($firstCookie)
        ->withCookie($secondCookie);

    expect($decorated->headerLines())->toBe([
        'Set-Cookie: ' . $thirdCookie->toSetCookieString(),
        'Set-Cookie: ' . $firstCookie->toSetCookieString(),
        'Set-Cookie: ' . $secondCookie->toSetCookieString(),
    ]);
});
