<?php

declare(strict_types=1);

use Marko\Routing\Http\Response;

it('accepts status code, headers, and body', function () {
    $response = new Response(
        body: 'Hello World',
        statusCode: 201,
        headers: ['X-Custom-Header' => 'custom-value'],
    );

    expect($response->body())->toBe('Hello World')
        ->and($response->statusCode())->toBe(201)
        ->and($response->headers())->toBe(['X-Custom-Header' => 'custom-value']);
});

it('defaults to 200 status code', function () {
    $response = new Response(body: 'Hello');

    expect($response->statusCode())->toBe(200)
        ->and($response->headers())->toBe([]);
});

it('creates JSON response with correct content-type', function () {
    $data = ['name' => 'John', 'age' => 30];
    $response = Response::json($data);

    expect($response->body())->toBe('{"name":"John","age":30}')
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json')
        ->and($response->statusCode())->toBe(200);
});

it('creates JSON response with custom status code', function () {
    $data = ['error' => 'Not Found'];
    $response = Response::json($data, 404);

    expect($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('application/json');
});

it('creates HTML response with correct content-type', function () {
    $html = '<html><body>Hello World</body></html>';
    $response = Response::html($html);

    expect($response->body())->toBe($html)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
        ->and($response->statusCode())->toBe(200);
});

it('creates HTML response with custom status code', function () {
    $html = '<html><body>Not Found</body></html>';
    $response = Response::html($html, 404);

    expect($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8');
});

it('creates redirect response with Location header', function () {
    $response = Response::redirect('/dashboard');

    expect($response->headers())->toHaveKey('Location')
        ->and($response->headers()['Location'])->toBe('/dashboard')
        ->and($response->statusCode())->toBe(302)
        ->and($response->body())->toBe('');
});

it('creates redirect response with custom status code', function () {
    $response = Response::redirect('/new-location', 301);

    expect($response->statusCode())->toBe(301)
        ->and($response->headers()['Location'])->toBe('/new-location');
});

it('send outputs headers and body', function () {
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
