<?php

declare(strict_types=1);

use Marko\Forms\Tests\Fixtures\FormsHarness;
use Marko\Forms\ValidationErrorResponder;
use Marko\Routing\Attributes\HttpExceptionResponder;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Http\HttpExceptionResponderInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\UploadedFile;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Exceptions\ValidationException;
use Marko\Validation\Validation\ValidationErrors;

describe('ValidationErrorResponder', function (): void {
    it('is a discoverable HTTP exception responder', function (): void {
        $attributes = new ReflectionClass(ValidationErrorResponder::class)->getAttributes(
            HttpExceptionResponder::class,
        );

        expect(new FormsHarness()->responder)->toBeInstanceOf(HttpExceptionResponderInterface::class)
            ->and($attributes)->toHaveCount(1);
    });

    it('passes on HTTP exceptions other than ValidationException', function (): void {
        $harness = new FormsHarness();

        expect($harness->responder->respond(HttpException::notFound(), FormsHarness::post()))->toBeNull();
    });

    it('redirects a failed POST back to the same-origin Referer with 303', function (): void {
        $harness = new FormsHarness();

        $response = $harness->failValidation(FormsHarness::post(
            ['email' => ''],
            ['HTTP_REFERER' => 'https://example.com/register?plan=pro'],
        ));

        expect($response?->statusCode())->toBe(303)
            ->and($response?->headers()['Location'])->toBe('/register?plan=pro');
    });

    it('flashes the errors and old input for the next request', function (): void {
        $harness = new FormsHarness();

        $harness->failValidation(FormsHarness::post(
            ['name' => 'Ann', 'email' => 'not-an-email'],
            ['HTTP_REFERER' => 'https://example.com/register'],
        ), ['email' => ['The email must be a valid email address.']]);
        $harness->nextRequest();

        expect($harness->state->hasErrors())->toBeTrue()
            ->and($harness->state->errors()->all())->toBe(['email' => ['The email must be a valid email address.']])
            ->and($harness->state->old('name'))->toBe('Ann')
            ->and($harness->state->old('email'))->toBe('not-an-email');
    });

    it('drops the flashed state on the request after the next one', function (): void {
        $harness = new FormsHarness();

        $harness->failValidation(FormsHarness::post(['name' => 'Ann']));
        $harness->nextRequest();
        $harness->nextRequest();

        expect($harness->state->hasErrors())->toBeFalse()
            ->and($harness->state->old('name'))->toBeNull();
    });

    it('never flashes passwords, the CSRF token or uploaded files', function (): void {
        $harness = new FormsHarness();
        $avatar = new UploadedFile('/tmp/php123', 'me.png', 'image/png', 10);

        $harness->failValidation(FormsHarness::post([
            'name' => 'Ann',
            '_token' => 'secret-token',
            'password' => 'hunter2',
            'password_confirmation' => 'hunter2',
            'current_password' => 'old',
            'avatar' => $avatar,
            'gallery' => [$avatar, 'caption'],
        ]));
        $harness->nextRequest();

        expect($harness->state->old('name'))->toBe('Ann')
            ->and($harness->state->old('_token'))->toBeNull()
            ->and($harness->state->old('password'))->toBeNull()
            ->and($harness->state->old('password_confirmation'))->toBeNull()
            ->and($harness->state->old('current_password'))->toBeNull()
            ->and($harness->state->old('avatar'))->toBeNull()
            ->and($harness->state->old('gallery'))->toBe([1 => 'caption']);
    });

    it('redirects to the fallback when the Referer has another origin', function (string $referer): void {
        $harness = new FormsHarness();

        $response = $harness->failValidation(FormsHarness::post(headers: ['HTTP_REFERER' => $referer]));

        expect($response?->statusCode())->toBe(303)
            ->and($response?->headers()['Location'])->toBe('/home');
    })->with([
        'other host' => 'https://evil.example/register',
        'subdomain' => 'https://evil.example.com/register',
        'other port' => 'https://example.com:8443/register',
        'protocol-relative' => '//evil.example/register',
        'not http' => 'javascript://example.com/%0Aalert(1)',
        'relative path' => '/register',
        'garbage' => 'http:///nothing',
    ]);

    it('redirects to the fallback when the request has no Referer', function (): void {
        $harness = new FormsHarness();

        $response = $harness->failValidation(FormsHarness::post());

        expect($response?->statusCode())->toBe(303)
            ->and($response?->headers()['Location'])->toBe('/home');
    });

    it('redirects to the fallback when the request has no Host', function (): void {
        $harness = new FormsHarness();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_REFERER' => 'https://example.com/register'],
        );

        $response = $harness->failValidation($request);

        expect($response?->headers()['Location'])->toBe('/home');
    });

    it(
        'matches the Referer origin ignoring case, default ports and scheme',
        function (string $referer, string $host): void {
            $harness = new FormsHarness();

            $response = $harness->failValidation(FormsHarness::post(
                headers: ['HTTP_REFERER' => $referer, 'HTTP_HOST' => $host],
            ));

            expect($response?->headers()['Location'])->toBe('/register');
        },
    )->with([
        'case' => ['https://EXAMPLE.com/register', 'example.COM'],
        'default port' => ['https://example.com:443/register', 'example.com'],
        'explicit port on both' => ['http://localhost:8000/register', 'localhost:8000'],
        'tls proxy' => ['https://example.com/register', 'example.com:80'],
    ]);

    it('never redirects to a protocol-relative path taken from the Referer', function (): void {
        $harness = new FormsHarness();

        $response = $harness->failValidation(FormsHarness::post(
            headers: ['HTTP_REFERER' => 'https://example.com//evil.example/x'],
        ));

        expect($response?->headers()['Location'])->toBe('/evil.example/x');
    });

    it(
        'passes for requests that want JSON or send JSON so they get the 422 response',
        function (array $headers): void {
            $harness = new FormsHarness();

            $response = $harness->failValidation(FormsHarness::post(['name' => 'Ann'], $headers));
            $harness->nextRequest();

            expect($response)->toBeNull()
                ->and($harness->state->hasErrors())->toBeFalse();
        },
    )->with([
        'accepts json' => [['HTTP_ACCEPT' => 'application/json']],
        'json body' => [['CONTENT_TYPE' => 'application/json']],
    ]);

    it('passes when the route has no session', function (): void {
        $harness = new FormsHarness();
        $responder = new ValidationErrorResponder(new FakeSession(), $harness->writer, $harness->config);
        $exception = ValidationException::withErrors(new ValidationErrors(['email' => ['Required.']]));

        expect($responder->respond($exception, FormsHarness::post()))->toBeNull();
    });

    it('works with a session that is armed but not started yet', function (): void {
        $harness = new FormsHarness();
        $session = new FakeSession();
        $session->arm();
        $responder = new ValidationErrorResponder($session, $harness->writer, $harness->config);
        $exception = ValidationException::withErrors(new ValidationErrors(['email' => ['Required.']]));

        $response = $responder->respond($exception, FormsHarness::post());

        expect($response?->statusCode())->toBe(303);
    });
});
