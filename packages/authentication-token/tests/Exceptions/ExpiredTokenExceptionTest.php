<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Exceptions;

use DateTimeImmutable;
use Exception;
use Marko\AuthenticationToken\Exceptions\ExpiredTokenException;

it('throws ExpiredTokenException with context and suggestion for expired tokens', function (): void {
    $token = 'abc123def456';
    $expiredAt = new DateTimeImmutable('2025-01-01 00:00:00');

    $exception = ExpiredTokenException::forToken($token, $expiredAt);

    expect($exception)->toBeInstanceOf(ExpiredTokenException::class)
        ->and($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->not->toBeEmpty()
        ->and($exception->getContext())->toContain($token)
        ->and($exception->getContext())->toContain('2025-01-01')
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
