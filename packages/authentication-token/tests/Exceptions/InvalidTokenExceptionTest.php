<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Tests\Exceptions;

use Exception;
use Marko\AuthenticationToken\Exceptions\InvalidTokenException;

it('throws InvalidTokenException with context for malformed token format', function (): void {
    $token = 'bad-token-format';

    $exception = InvalidTokenException::forToken($token);

    expect($exception)->toBeInstanceOf(InvalidTokenException::class)
        ->and($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->not->toBeEmpty()
        ->and($exception->getContext())->toContain($token)
        ->and($exception->getSuggestion())->not->toBeEmpty();
});
