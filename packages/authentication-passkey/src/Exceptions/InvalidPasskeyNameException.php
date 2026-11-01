<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * A passkey name is empty or too long. Rendered as 422.
 */
class InvalidPasskeyNameException extends HttpException
{
    public const int MAX_LENGTH = 255;

    public static function forName(
        string $name,
    ): self {
        return new self(
            statusCode: 422,
            message: 'A passkey name must be 1 to ' . self::MAX_LENGTH . ' characters.',
            context: sprintf('Got a name of %d characters after trimming', mb_strlen(trim($name))),
            suggestion: 'Ask the user for a short label such as "MacBook Touch ID" or "YubiKey"',
        );
    }
}
