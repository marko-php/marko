<?php

declare(strict_types=1);

use Marko\Http\Exceptions\ConnectionException;
use Marko\Http\Exceptions\HttpException;
use Marko\Http\HttpResponse;

describe('HttpException', function (): void {
    it('defines HttpException with response access', function (): void {
        $response = new HttpResponse(
            statusCode: 404,
            body: 'Not Found',
        );
        $exception = new HttpException(
            'Resource not found',
            $response,
        );

        expect($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getMessage())->toBe('Resource not found')
            ->and($exception->getResponse())->toBe($response)
            ->and($exception->getResponse()->statusCode())->toBe(404);
    });

    it('allows null response on HttpException', function (): void {
        $exception = new HttpException('Connection failed');

        expect($exception->getResponse())->toBeNull();
    });
});

describe('ConnectionException', function (): void {
    it('defines ConnectionException extending HttpException', function (): void {
        $exception = new ConnectionException('Could not connect to host');

        expect($exception)->toBeInstanceOf(ConnectionException::class)
            ->and($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getMessage())->toBe('Could not connect to host')
            ->and($exception->getResponse())->toBeNull();
    });
});
