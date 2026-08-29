<?php

declare(strict_types=1);

use Marko\Inertia\Exceptions\InertiaConfigurationException;
use Marko\Inertia\Middleware\InertiaMiddleware;

use function Marko\Inertia\Tests\createTaggedResponse;

use Marko\Inertia\Tests\TaggedResponse;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

use Marko\Testing\Fake\FakeConfigRepository;

beforeEach(function (): void {
    $this->middleware = new InertiaMiddleware(new FakeConfigRepository([
        'inertia.version' => '1.0',
    ]));
});

test('middleware passes through non-inertia requests unchanged', function (): void {
    $request = new Request();
    $originalResponse = new Response(body: 'OK');

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->body())->toBe('OK')
        ->and($response->headers())->not->toHaveKey('X-Inertia');
});

test('middleware adds inertia headers for inertia requests', function (): void {
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $originalResponse = new Response(body: '{}', headers: ['Content-Type' => 'application/json']);

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->headers()['X-Inertia'])->toBe('true')
        ->and($response->headers()['Vary'])->toBe('X-Inertia');
});

test('middleware leaves redirects unchanged for inertia requests', function (): void {
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $originalResponse = Response::redirect('/other');

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/other')
        ->and($response->headers()['Vary'])->toBe('X-Inertia')
        ->and($response->headers())->not->toHaveKey('X-Inertia-Location');
});

test('middleware upgrades non-get inertia redirects to 303', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'PATCH',
        'HTTP_X_INERTIA' => 'true',
    ]);
    $originalResponse = Response::redirect('/updated');

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->statusCode())->toBe(303)
        ->and($response->headers()['Location'])->toBe('/updated')
        ->and($response->headers()['Vary'])->toBe('X-Inertia');
});

test('middleware returns 409 on version mismatch', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);
    $originalResponse = new Response(body: '{}');

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->statusCode())->toBe(409)
        ->and($response->headers()['X-Inertia-Location'])->toBe('/');
});

it('includes the query string in the 409 X-Inertia-Location header', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/users?page=2',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);

    $response = $this->middleware->handle($request, fn (): Response => new Response(body: '{}'));

    expect($response->statusCode())->toBe(409)
        ->and($response->headers()['X-Inertia-Location'])->toBe('/users?page=2');
});

it('returns a 409 on an asset-version mismatch', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);

    $response = $this->middleware->handle($request, fn (): Response => new Response(body: '{}'));

    expect($response->statusCode())->toBe(409);
});

it('does not lose flash data on an asset-version mismatch', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);

    $controllerWasCalled = false;
    $next = function () use (&$controllerWasCalled): Response {
        $controllerWasCalled = true;

        return new Response(body: '{}');
    };

    $response = $this->middleware->handle($request, $next);

    expect($response->statusCode())->toBe(409)
        ->and($controllerWasCalled)->toBeFalse();
});

test('middleware does not return 409 for non-get version mismatches', function (): void {
    $request = new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_INERTIA' => 'true',
        'HTTP_X_INERTIA_VERSION' => '0.9',
    ]);
    $originalResponse = new Response(body: '{}');

    $response = $this->middleware->handle($request, fn (): Response => $originalResponse);

    expect($response->statusCode())->toBe(200)
        ->and($response->headers()['X-Inertia'])->toBe('true');
});

test('middleware throws a loud exception for invalid version config', function (): void {
    $middleware = new InertiaMiddleware(new FakeConfigRepository([
        'inertia.version' => ['invalid'],
    ]));

    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);

    expect(fn (): Response => $middleware->handle($request, fn (): Response => new Response(body: '{}')))
        ->toThrow(
            InertiaConfigurationException::class,
            'Inertia configuration key "inertia.version" must be a string, number, or null.',
        );
});

it('preserves the response subclass through inertia middleware', function (): void {
    $request = new Request(server: ['HTTP_X_INERTIA' => 'true']);
    $originalResponse = createTaggedResponse(tag: 'from-controller', body: '{}');

    $response = $this->middleware->handle($request, fn (): TaggedResponse => $originalResponse);

    /** @var TaggedResponse $response */
    expect($response)
        ->toBeInstanceOf(TaggedResponse::class)
        ->and($response->tag)->toBe('from-controller')
        ->and($response->headers()['X-Inertia'])->toBe('true');
});

it(
    'preserves the response subclass through the inertia redirect branch while upgrading 302 to 303',
    function (): void {
        $request = new Request(server: [
            'REQUEST_METHOD' => 'PATCH',
            'HTTP_X_INERTIA' => 'true',
        ]);
        $originalResponse = createTaggedResponse(
            tag: 'from-controller',
            statusCode: 302,
            headers: ['Location' => '/updated'],
        );

        $response = $this->middleware->handle($request, fn (): TaggedResponse => $originalResponse);

        /** @var TaggedResponse $response */
        expect($response)
            ->toBeInstanceOf(TaggedResponse::class)
            ->and($response->tag)->toBe('from-controller')
            ->and($response->statusCode())->toBe(303)
            ->and($response->headers()['Location'])->toBe('/updated');
    },
);
