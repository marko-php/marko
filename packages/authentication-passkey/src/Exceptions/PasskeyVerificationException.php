<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\AuthenticationPasskey\Event\PasskeyFailureReason;
use Marko\Routing\Exceptions\HttpException;
use Throwable;

/**
 * A WebAuthn response was rejected: malformed, signed for another challenge,
 * origin or relying party, or with a signature that does not verify.
 *
 * Rendered as 400 Bad Request. PasskeyAuthentication turns it into a
 * PasskeyAuthenticationFailedException, so a sign-in failure never tells the
 * client why it failed; getReason() is for logs and tests.
 */
class PasskeyVerificationException extends HttpException
{
    private PasskeyFailureReason $reason = PasskeyFailureReason::MalformedResponse;

    public static function because(
        PasskeyFailureReason $reason,
        string $detail,
        ?Throwable $previous = null,
    ): self {
        $exception = new self(
            statusCode: 400,
            message: 'The passkey response could not be verified.',
            context: "Passkey verification failed ($reason->value): $detail",
            suggestion: self::suggestionFor($reason),
            previous: $previous,
        );
        $exception->reason = $reason;

        return $exception;
    }

    public function getReason(): PasskeyFailureReason
    {
        return $this->reason;
    }

    private static function suggestionFor(
        PasskeyFailureReason $reason,
    ): string {
        return match ($reason) {
            PasskeyFailureReason::ChallengeMismatch => 'Request fresh options (creationOptions() or requestOptions()) for every ceremony and send the response before authentication-passkey.timeout_seconds pass; each challenge works once',
            PasskeyFailureReason::OriginMismatch => 'Add the page origin (scheme, host and port, e.g. https://app.example.com) to authentication-passkey.allowed_origins',
            PasskeyFailureReason::RelyingPartyMismatch => 'Set authentication-passkey.relying_party.id to the domain the passkey was created for',
            PasskeyFailureReason::UserNotVerified => 'The authenticator must verify the user with a PIN or biometric; passkeys are always requested with userVerification "required"',
            PasskeyFailureReason::MalformedResponse => 'Send the JSON of the PublicKeyCredential (credential.toJSON(), or the bundled passkeys.js helper) unchanged',
            default => 'Ask the user to try again with a passkey registered for this account',
        };
    }
}
