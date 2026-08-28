<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;

it('creates request from PHP superglobals', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test';
    $_GET = ['foo' => 'bar'];
    $_POST = [];
    $_COOKIE = [];

    $request = Request::fromGlobals();

    expect($request)->toBeInstanceOf(Request::class);
});

it('returns method (GET, POST, etc.) from server vars', function (): void {
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $request = Request::fromGlobals();

    expect($request->method())->toBe('POST');
});

it('returns path without query string', function (): void {
    $_SERVER['REQUEST_URI'] = '/users/123?page=1&sort=name';

    $request = Request::fromGlobals();

    expect($request->path())->toBe('/users/123');
});

it('returns query parameters from GET', function (): void {
    $_GET = ['page' => '1', 'sort' => 'name'];

    $request = Request::fromGlobals();

    expect($request->query())->toBe(['page' => '1', 'sort' => 'name'])
        ->and($request->query('page'))->toBe('1')
        ->and($request->query('missing'))->toBeNull()
        ->and($request->query('missing', 'default'))->toBe('default');
});

it('returns body parameters from POST', function (): void {
    $_POST = ['name' => 'John', 'email' => 'john@example.com'];

    $request = Request::fromGlobals();

    expect($request->post())->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and($request->post('name'))->toBe('John')
        ->and($request->post('missing'))->toBeNull()
        ->and($request->post('missing', 'default'))->toBe('default');
});

it('returns specific header by name', function (): void {
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

it('returns all headers', function (): void {
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

it('returns a raw server param by key via server()', function (): void {
    $request = new Request(server: ['SERVER_NAME' => 'example.com', 'SERVER_PORT' => '443']);

    expect($request->server('SERVER_NAME'))->toBe('example.com')
        ->and($request->server('SERVER_PORT'))->toBe('443');
});

it('returns null from server() when the key is absent', function (): void {
    $request = new Request(server: ['SERVER_NAME' => 'example.com']);

    expect($request->server('MISSING_KEY'))->toBeNull();
});

it('returns the REMOTE_ADDR value via ip()', function (): void {
    $request = new Request(server: ['REMOTE_ADDR' => '192.168.1.1']);

    expect($request->ip())->toBe('192.168.1.1');
});

it('returns null from ip() when REMOTE_ADDR is absent', function (): void {
    $request = new Request(server: ['SERVER_NAME' => 'example.com']);

    expect($request->ip())->toBeNull();
});

it('ignores X-Forwarded-For when resolving ip()', function (): void {
    $request = new Request(server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.1',
    ]);

    expect($request->ip())->toBe('10.0.0.1');
});

it('returns a new Request carrying the controller and action via withRoute()', function (): void {
    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $routed = $request->withRoute('App\\Controllers\\HomeController', 'index');

    expect($routed)->toBeInstanceOf(Request::class)
        ->and($routed->controller())->toBe('App\\Controllers\\HomeController')
        ->and($routed->action())->toBe('index');
});

it('leaves the original Request unchanged after withRoute()', function (): void {
    $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']);

    $routed = $request->withRoute('App\\Controllers\\HomeController', 'index');

    expect($request->controller())->toBeNull()
        ->and($request->action())->toBeNull()
        ->and($routed)->not->toBe($request);
});

it('returns null from controller() and action() before withRoute() is called', function (): void {
    $request = new Request(server: ['REQUEST_METHOD' => 'GET']);

    expect($request->controller())->toBeNull()
        ->and($request->action())->toBeNull();
});

it('reads Content-Type from the CGI CONTENT_TYPE server key when HTTP_CONTENT_TYPE is absent', function (): void {
    $request = new Request(server: ['CONTENT_TYPE' => 'application/json']);

    expect($request->header('Content-Type'))->toBe('application/json');
});

it('still reads Content-Type from HTTP_CONTENT_TYPE when present', function (): void {
    $request = new Request(server: ['HTTP_CONTENT_TYPE' => 'text/html', 'CONTENT_TYPE' => 'application/json']);

    expect($request->header('Content-Type'))->toBe('text/html');
});

it('reads Content-Length from the CGI CONTENT_LENGTH server key', function (): void {
    $request = new Request(server: ['CONTENT_LENGTH' => '42']);

    expect($request->header('Content-Length'))->toBe('42');
});

it('does not read an un-prefixed key for a non-CGI header name', function (): void {
    $request = new Request(server: ['X_CUSTOM' => 'should-not-appear']);

    expect($request->header('X-Custom'))->toBeNull();
});

it('returns the default when neither header form is present', function (): void {
    $request = new Request(server: []);

    expect($request->header('Content-Type', 'text/plain'))->toBe('text/plain');
});

it('returns all cookies when no key is given', function (): void {
    $request = new Request(cookies: ['session_id' => 'abc123', 'theme' => 'dark']);

    expect($request->cookie())->toBe(['session_id' => 'abc123', 'theme' => 'dark']);
});

it('returns a single cookie value by name', function (): void {
    $request = new Request(cookies: ['session_id' => 'abc123']);

    expect($request->cookie('session_id'))->toBe('abc123');
});

it('returns the default when the cookie is absent', function (): void {
    $request = new Request(cookies: ['session_id' => 'abc123']);

    expect($request->cookie('missing'))->toBeNull()
        ->and($request->cookie('missing', 'default'))->toBe('default');
});

it('defaults to an empty cookie collection', function (): void {
    $request = new Request();

    expect($request->cookie())->toBeEmpty();
});

it('captures cookies from globals', function (): void {
    $_COOKIE = ['session_id' => 'abc123'];

    $request = Request::fromGlobals();

    expect($request->cookie('session_id'))->toBe('abc123');
});

it('preserves cookies through withRoute', function (): void {
    $request = new Request(server: ['REQUEST_METHOD' => 'GET'], cookies: ['session_id' => 'abc123']);

    $routed = $request->withRoute('App\\Controllers\\HomeController', 'index');

    expect($routed->cookie('session_id'))->toBe('abc123');
});
