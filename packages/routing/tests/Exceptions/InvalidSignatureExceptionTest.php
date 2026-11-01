<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Exceptions\InvalidSignatureException;

describe('InvalidSignatureException', function (): void {
    it('is a 403 HttpException for an invalid signature', function (): void {
        $exception = InvalidSignatureException::invalid();

        expect($exception)->toBeInstanceOf(HttpException::class)
            ->and($exception->getStatusCode())->toBe(403)
            ->and($exception->getMessage())->toBe('This link is invalid.')
            ->and($exception->isExpired())->toBeFalse();
    });

    it('is a 403 HttpException for an expired link', function (): void {
        $exception = InvalidSignatureException::expired();

        expect($exception->getStatusCode())->toBe(403)
            ->and($exception->getMessage())->toBe('This link has expired.')
            ->and($exception->isExpired())->toBeTrue();
    });
});
