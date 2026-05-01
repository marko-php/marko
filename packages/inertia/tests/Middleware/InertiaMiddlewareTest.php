<?php

declare(strict_types=1);

use Marko\Inertia\Exceptions\InertiaConfigurationException;
use Marko\Inertia\Middleware\InertiaMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeConfigRepository;

beforeEach(function () {
    $this->config = new FakeConfigRepository([
        'inertia.version' => '1.0',
    ]);
    $this->middleware = new InertiaMiddleware($this->config);
});

test('middleware passes through non-inertia requests unchanged', function () {
    $request = new Request();
    $originalResponse = new Response(body: 'OK');

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->body())->toBe('OK');
    expect($response->headers())->not->toHaveKey('X-Inertia');
});

test('middleware adds inertia headers for inertia requests', function () {
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $originalResponse = new Response(body: '{}', headers: ['Content-Type' => 'application/json']);

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->headers()['X-Inertia'])->toBe('true');
    expect($response->headers()['Vary'])->toBe('X-Inertia');
});

test('middleware leaves redirects unchanged for inertia requests', function () {
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $originalResponse = Response::redirect('/other');

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->statusCode())->toBe(302);
    expect($response->headers()['Location'])->toBe('/other');
    expect($response->headers()['Vary'])->toBe('X-Inertia');
    expect($response->headers())->not->toHaveKey('X-Inertia-Location');
});

test('middleware upgrades non-get inertia redirects to 303', function () {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'PATCH',
        'HTTP_X_INERTIA' => 'true',
    ]);
    $originalResponse = Response::redirect('/updated');

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->statusCode())->toBe(303);
    expect($response->headers()['Location'])->toBe('/updated');
    expect($response->headers()['Vary'])->toBe('X-Inertia');
});

test('middleware returns 409 on version mismatch', function () {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);
    $originalResponse = new Response(body: '{}');

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->statusCode())->toBe(409);
    expect($response->headers()['X-Inertia-Location'])->toBe('/');
});

test('middleware does not return 409 for non-get version mismatches', function () {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);
    $originalResponse = new Response(body: '{}');

    $response = $this->middleware->handle($request, fn () => $originalResponse);

    expect($response->statusCode())->toBe(200);
    expect($response->headers()['X-Inertia'])->toBe('true');
});

test('middleware throws a loud exception for invalid version config', function () {
    $middleware = new InertiaMiddleware(new FakeConfigRepository([
        'inertia.version' => ['invalid'],
    ]));

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    expect(fn () => $middleware->handle($request, fn () => new Response(body: '{}')))
        ->toThrow(
            InertiaConfigurationException::class,
            'Inertia configuration key "inertia.version" must be a string, number, or null.',
        );
});
