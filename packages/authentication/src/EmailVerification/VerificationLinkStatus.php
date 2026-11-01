<?php

declare(strict_types=1);

namespace Marko\Authentication\EmailVerification;

enum VerificationLinkStatus: string
{
    /** A verification link was sent. */
    case Sent = 'sent';

    /** A link was already sent within authentication.verification.throttle_seconds; nothing was sent. */
    case Throttled = 'throttled';

    /** The user's email is already verified; nothing was sent. */
    case AlreadyVerified = 'already_verified';
}
