<?php

declare(strict_types=1);

namespace Marko\Authentication\PasswordReset;

enum ResetStatus: string
{
    /** The password was changed and the token used up. */
    case PasswordReset = 'password_reset';

    /** The token is wrong, already used, or older than authentication.password_reset.expire_seconds. */
    case InvalidToken = 'invalid_token';

    /** No user matches the credentials. */
    case InvalidUser = 'invalid_user';
}
