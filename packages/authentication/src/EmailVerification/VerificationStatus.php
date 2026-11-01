<?php

declare(strict_types=1);

namespace Marko\Authentication\EmailVerification;

enum VerificationStatus: string
{
    /** The email was verified and the user saved; EmailVerifiedEvent was dispatched. */
    case Verified = 'verified';

    /** The email was already verified; nothing changed and no event was dispatched. */
    case AlreadyVerified = 'already_verified';
}
