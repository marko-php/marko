<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Exceptions;

use DateTimeInterface;

class ExpiredTokenException extends TokenException
{
    public static function forToken(
        string $token,
        DateTimeInterface $expiredAt,
    ): self {
        $expiredAtFormatted = $expiredAt->format('Y-m-d H:i:s');

        return new self(
            message: 'Token has expired',
            context: "The token '$token' expired at $expiredAtFormatted",
            suggestion: 'Please generate a new personal access token',
        );
    }
}
