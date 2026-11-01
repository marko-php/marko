<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Event;

/**
 * Why a passkey registration or sign-in was rejected.
 */
enum PasskeyFailureReason: string
{
    /** The browser's response is not a well-formed WebAuthn credential. */
    case MalformedResponse = 'malformed_response';

    /** No challenge is pending, it expired, it was already used, or the response signs a different one. */
    case ChallengeMismatch = 'challenge_mismatch';

    /** The response comes from an origin that is not in allowed_origins. */
    case OriginMismatch = 'origin_mismatch';

    /** The authenticator data was produced for a different relying party id. */
    case RelyingPartyMismatch = 'relying_party_mismatch';

    /** The authenticator did not verify the user (PIN or biometric). */
    case UserNotVerified = 'user_not_verified';

    /** The attestation or assertion signature does not verify. */
    case InvalidSignature = 'invalid_signature';

    /** No stored passkey has this credential id for the guard's user provider. */
    case UnknownCredential = 'unknown_credential';

    /** The response's user handle names a different user than the stored passkey's owner. */
    case UserHandleMismatch = 'user_handle_mismatch';

    /** The signature counter did not increase, a sign the authenticator may have been cloned. */
    case SignCountRegressed = 'sign_count_regressed';

    /** The passkey's owner no longer exists in the user provider. */
    case UnknownUser = 'unknown_user';

    /** The credential id is already registered. */
    case CredentialAlreadyRegistered = 'credential_already_registered';
}
