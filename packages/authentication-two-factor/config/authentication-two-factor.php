<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    |
    | The name authenticator apps show next to the account, usually your app
    | name. It is written into the otpauth:// provisioning URI. Required:
    | enabling two-factor throws while it is not set.
    |
    */
    'issuer' => Env::nullableString('TWO_FACTOR_ISSUER'),

    /*
    |--------------------------------------------------------------------------
    | Setup Route
    |--------------------------------------------------------------------------
    |
    | Where RequireTwoFactorMiddleware redirects a browser request from a user
    | who has not confirmed two-factor yet. JSON requests get a 403 instead.
    |
    */
    'setup_route' => '/two-factor/setup',

    /*
    |--------------------------------------------------------------------------
    | Recovery Codes
    |--------------------------------------------------------------------------
    |
    | How many single-use recovery codes enable() and
    | regenerateRecoveryCodes() issue. Must be 1 or more.
    |
    */
    'recovery_code_count' => 8,

    /*
    |--------------------------------------------------------------------------
    | Challenge Throttling
    |--------------------------------------------------------------------------
    |
    | A pending user may try max_attempts codes within decay_seconds before
    | TwoFactorChallenge::verify() throws TooManyTwoFactorAttemptsException
    | (429 with Retry-After). Counted per guard and user through
    | marko/ratelimiter. Both must be 1 or more.
    |
    */
    'throttle' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
