<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Security\Middleware\CsrfMiddleware;

function createStubTokenManager(
    string $storedToken = 'valid-csrf-token',
): CsrfTokenManagerInterface {
    return new class ($storedToken) implements CsrfTokenManagerInterface
    {
        public function __construct(
            private readonly string $storedToken,
        ) {}

        public function get(): string
        {
            return $this->storedToken;
        }

        public function validate(
            string $token,
        ): bool {
            return hash_equals($this->storedToken, $token);
        }

        public function regenerate(): string
        {
            return $this->storedToken;
        }
    };
}

describe('CsrfMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager(),
        );

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('passes GET requests through without validation', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager(),
        );

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK');
    });

    it('passes HEAD and OPTIONS requests through without validation', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager(),
        );

        $headRequest = new Request(server: ['REQUEST_METHOD' => 'HEAD']);
        $optionsRequest = new Request(server: ['REQUEST_METHOD' => 'OPTIONS']);
        $next = fn (Request $r) => new Response('OK', 200);

        $headResponse = $middleware->handle($headRequest, $next);
        $optionsResponse = $middleware->handle($optionsRequest, $next);

        expect($headResponse->statusCode())->toBe(200)
            ->and($optionsResponse->statusCode())->toBe(200);
    });

    it('validates token from _token POST field on POST request', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager('my-token'),
        );

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST'],
            post: ['_token' => 'my-token'],
        );
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK');
    });

    it('validates token from X-CSRF-TOKEN header on POST request', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager('my-token'),
        );

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'POST',
                'HTTP_X_CSRF_TOKEN' => 'my-token',
            ],
        );
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK');
    });

    it('throws CsrfTokenMismatchException when token is missing on POST', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager(),
        );

        $request = new Request(server: ['REQUEST_METHOD' => 'POST']);
        $next = fn (Request $r) => new Response('OK', 200);

        $middleware->handle($request, $next);
    })->throws(CsrfTokenMismatchException::class);

    it('throws CsrfTokenMismatchException when token is invalid on PUT PATCH DELETE', function (): void {
        $middleware = new CsrfMiddleware(
            tokenManager: createStubTokenManager('valid-token'),
        );

        $next = fn (Request $r) => new Response('OK', 200);

        // Test PUT
        $putRequest = new Request(
            server: ['REQUEST_METHOD' => 'PUT'],
            post: ['_token' => 'wrong-token'],
        );

        $threw = false;

        try {
            $middleware->handle($putRequest, $next);
        } catch (CsrfTokenMismatchException) {
            $threw = true;
        }

        expect($threw)->toBeTrue();

        // Test PATCH
        $patchRequest = new Request(
            server: ['REQUEST_METHOD' => 'PATCH'],
            post: ['_token' => 'wrong-token'],
        );

        $threw = false;

        try {
            $middleware->handle($patchRequest, $next);
        } catch (CsrfTokenMismatchException) {
            $threw = true;
        }

        expect($threw)->toBeTrue();

        // Test DELETE
        $deleteRequest = new Request(
            server: ['REQUEST_METHOD' => 'DELETE'],
            post: ['_token' => 'wrong-token'],
        );

        $threw = false;

        try {
            $middleware->handle($deleteRequest, $next);
        } catch (CsrfTokenMismatchException) {
            $threw = true;
        }

        expect($threw)->toBeTrue();
    });
});
