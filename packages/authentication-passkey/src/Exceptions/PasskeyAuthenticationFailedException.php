<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\Routing\Exceptions\HttpException;

/**
 * A passkey sign-in or confirmation was rejected. Rendered as 401 with the
 * same message whatever the reason, so the response reveals nothing about
 * which passkeys exist; getReason() and the previous exception carry the cause.
 */
class PasskeyAuthenticationFailedException extends HttpException
{
    private PasskeyFailureReason $reason = PasskeyFailureReason::MalformedResponse;

    public static function fromVerification(
        PasskeyVerificationException $exception,
    ): self {
        $failed = new self(
            statusCode: 401,
            message: 'Passkey sign-in failed.',
            context: $exception->getContext(),
            suggestion: $exception->getSuggestion(),
            previous: $exception,
        );
        $failed->reason = $exception->getReason();

        return $failed;
    }

    public function getReason(): PasskeyFailureReason
    {
        return $this->reason;
    }
}
