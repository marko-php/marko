<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordReset;

enum ResetLinkStatus: string
{
    /** A link was sent, or no user matched (the two are not told apart). */
    case Sent = 'sent';

    /** A link was already requested within authentication.password_reset.throttle_seconds. */
    case Throttled = 'throttled';
}
