<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;

it('creates request from PHP superglobals', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    $_GET = ['foo' => 'bar'];
    $_POST = [];
    $_COOKIE = [];

    $request = Request::fromGlobals();

    expect($request)->toBeInstanceOf(Request::class);
});

it('returns method (GET, POST, etc.) from server vars', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $request = Request::fromGlobals();

    expect($request->method())->toBe('POST');
});

it('returns path without query string', function () {
    $_SERVER['REQUEST_URI'] = '/users/123?page=1&sort=name';

    $request = Request::fromGlobals();

    expect($request->path())->toBe('/users/123');
});

it('returns query parameters from GET', function () {
    $_GET = ['page' => '1', 'sort' => 'name'];

    $request = Request::fromGlobals();

    expect($request->query())->toBe(['page' => '1', 'sort' => 'name'])
        ->and($request->query('page'))->toBe('1')
        ->and($request->query('missing'))->toBeNull()
        ->and($request->query('missing', 'default'))->toBe('default');
});

it('returns body parameters from POST', function () {
    $_POST = ['name' => 'John', 'email' => 'john@example.com'];

    $request = Request::fromGlobals();

    expect($request->post())->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and($request->post('name'))->toBe('John')
        ->and($request->post('missing'))->toBeNull()
        ->and($request->post('missing', 'default'))->toBe('default');
});

it('returns specific header by name', function () {
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';

    $request = Request::fromGlobals();

    expect($request->header('Content-Type'))->toBe('application/json')
        ->and($request->header('Accept'))->toBe('text/html')
        ->and($request->header('X-Custom-Header'))->toBe('custom-value')
        ->and($request->header('Missing-Header'))->toBeNull()
        ->and($request->header('Missing-Header', 'default'))->toBe('default');
});

it('returns all headers', function () {
    $_SERVER = [
        'HTTP_CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'text/html',
        'REQUEST_METHOD' => 'GET',
        'SERVER_NAME' => 'localhost',
    ];

    $request = Request::fromGlobals();
    $headers = $request->headers();

    expect($headers)->toHaveKey('Content-Type')
        ->and($headers)->toHaveKey('Accept')
        ->and($headers['Content-Type'])->toBe('application/json')
        ->and($headers['Accept'])->toBe('text/html')
        ->and($headers)->not->toHaveKey('Request-Method')
        ->and($headers)->not->toHaveKey('Server-Name');
});
