<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Relying Party
    |--------------------------------------------------------------------------
    |
    | The relying party is your site as passkeys know it. 'id' is a domain
    | (no scheme or port): passkeys created for it work on that domain and
    | its subdomains, and on nothing else, so changing it later orphans every
    | registered passkey. 'name' is shown by the browser while the user
    | creates a passkey. Both are required; nothing falls back to a default.
    |
    */
    'relying_party' => [
        'id' => Env::nullableString('PASSKEY_RP_ID'),
        'name' => Env::nullableString('PASSKEY_RP_NAME'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | The exact origins (scheme, host and port) your passkey pages are served
    | from, e.g. https://example.com. Each host must be the relying party id
    | or one of its subdomains, and each origin must use https; plain http is
    | accepted only for localhost while developing. A response from any other
    | origin is rejected. PASSKEY_ALLOWED_ORIGINS is a comma-separated list.
    |
    */
    'allowed_origins' => Env::list('PASSKEY_ALLOWED_ORIGINS', []),

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The guard (from authentication.guards) passkeys sign users in with. It
    | must be a session guard. Passkeys are stored with the name of this
    | guard's user provider.
    |
    */
    'guard' => 'session',

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | How long, in seconds, the browser waits for the user and a challenge
    | stays valid. A response that arrives later is rejected.
    |
    */
    'timeout_seconds' => 300,

    /*
    |--------------------------------------------------------------------------
    | Throttling
    |--------------------------------------------------------------------------
    |
    | A client (by IP, or by marko/ratelimiter's key resolver) may make
    | max_attempts sign-in or confirmation attempts within decay_seconds. A
    | successful one resets the count; going over throws
    | TooManyPasskeyAttemptsException (429 with Retry-After).
    |
    */
    'throttle' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
