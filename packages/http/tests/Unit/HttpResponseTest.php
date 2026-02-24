<?php

declare(strict_types=1);

use Marko\Http\HttpResponse;

describe('HttpResponse', function (): void {
    it('creates HttpResponse with status code body and headers', function (): void {
        $response = new HttpResponse(
            statusCode: 200,
            body: 'Hello World',
            headers: ['Content-Type' => 'text/plain'],
        );

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('Hello World')
            ->and($response->headers())->toBe(['Content-Type' => 'text/plain']);
    });

    it('returns json decoded body from HttpResponse', function (): void {
        $response = new HttpResponse(
            statusCode: 200,
            body: '{"name":"marko","version":1}',
        );

        expect($response->json())->toBe(['name' => 'marko', 'version' => 1]);
    });

    it('reports successful status codes from HttpResponse', function (): void {
        $ok = new HttpResponse(statusCode: 200, body: '');
        $created = new HttpResponse(statusCode: 201, body: '');

        expect($ok->isSuccessful())->toBeTrue()
            ->and($ok->isClientError())->toBeFalse()
            ->and($ok->isServerError())->toBeFalse()
            ->and($ok->isRedirect())->toBeFalse()
            ->and($created->isSuccessful())->toBeTrue();
    });

    it('reports client error status codes', function (): void {
        $badRequest = new HttpResponse(statusCode: 400, body: '');
        $notFound = new HttpResponse(statusCode: 404, body: '');

        expect($badRequest->isClientError())->toBeTrue()
            ->and($badRequest->isSuccessful())->toBeFalse()
            ->and($notFound->isClientError())->toBeTrue();
    });

    it('reports server error status codes', function (): void {
        $serverError = new HttpResponse(statusCode: 500, body: '');

        expect($serverError->isServerError())->toBeTrue()
            ->and($serverError->isSuccessful())->toBeFalse()
            ->and($serverError->isClientError())->toBeFalse();
    });

    it('reports redirect status codes', function (): void {
        $movedPermanently = new HttpResponse(statusCode: 301, body: '');
        $found = new HttpResponse(statusCode: 302, body: '');

        expect($movedPermanently->isRedirect())->toBeTrue()
            ->and($movedPermanently->isSuccessful())->toBeFalse()
            ->and($found->isRedirect())->toBeTrue();
    });
});
