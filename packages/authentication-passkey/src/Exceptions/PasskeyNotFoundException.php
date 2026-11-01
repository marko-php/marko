<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\Routing\Exceptions\HttpException;

/**
 * The signed-in user has no passkey with this credential id. Rendered as 404;
 * another user's passkey is reported the same way, so ids cannot be probed.
 */
class PasskeyNotFoundException extends HttpException
{
    public static function forCredentialId(
        string $credentialId,
    ): self {
        return new self(
            statusCode: 404,
            message: 'Passkey not found.',
            context: "The signed-in user has no passkey with credential id '$credentialId'",
            suggestion: 'List the user\'s passkeys with PasskeyManager::forCurrentUser() and use one of their credential ids',
        );
    }
}
