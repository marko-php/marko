<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | The default guard and provider used for authentication.
    |
    */
    'default' => [
        'guard' => 'session',
        'provider' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Guards define how users are authenticated for each request.
    | Each guard has a driver and a provider. The 'session' driver is
    | built in; the 'token' driver comes from marko/authentication-token.
    | Packages register more drivers with GuardDriverRegistry::extend().
    |
    */
    'guards' => [
        'session' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'token' => [
            'driver' => 'token',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Providers define where a guard loads its users from. A guard names its
    | provider with its 'provider' key. 'class' names a UserProviderInterface
    | implementation, resolved from the container. A provider without a
    | 'class' uses the app's UserProviderInterface binding.
    |
    | 'persister' names a UserPersisterInterface implementation that saves
    | this provider's users after a feature such as password reset changes
    | them. A provider without a 'persister' uses the app's
    | UserPersisterInterface binding.
    |
    */
    'providers' => [
        'users' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Hashing
    |--------------------------------------------------------------------------
    |
    | Configuration for password hashing. The bcrypt driver is recommended
    | for most applications.
    |
    */
    'password' => [
        'driver' => 'bcrypt',
        'bcrypt' => [
            'cost' => 12,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Remember Me
    |--------------------------------------------------------------------------
    |
    | Configuration for "remember me" functionality. The lifetime is in
    | minutes. Each guard writes its own cookie named {prefix}{guard}, e.g.
    | remember_session. Set 'secure' to null to follow the session cookie's
    | secure flag (session.cookie.secure).
    |
    */
    'remember' => [
        'lifetime' => 43200, // 30 days
        'cookie' => [
            'prefix' => 'remember_',
            'path' => '/',
            'domain' => '',
            'secure' => null,
            'http_only' => true,
            'same_site' => 'Lax',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Throttling
    |--------------------------------------------------------------------------
    |
    | SessionGuard::attempt() locks out a login identifier (e.g. the email)
    | for one client IP after max_attempts failures within decay_seconds.
    | The first lockout lasts lockout_seconds; each further one doubles, up
    | to max_lockout_seconds. A locked-out attempt throws
    | TooManyLoginAttemptsException (429 with Retry-After). Counters live in
    | the cache, so a cache driver is required while this is enabled.
    |
    */
    'throttle' => [
        'enabled' => true,
        'max_attempts' => 5,
        'decay_seconds' => 60,
        'lockout_seconds' => 60,
        'max_lockout_seconds' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset
    |--------------------------------------------------------------------------
    |
    | PasswordResetBroker looks users up in 'provider'. A reset token is
    | valid for expire_seconds after it is issued, and one user can request
    | at most one reset link every throttle_seconds. Both must be 1 or more.
    |
    */
    'password_reset' => [
        'provider' => 'users',
        'expire_seconds' => 3600,
        'throttle_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation
    |--------------------------------------------------------------------------
    |
    | RequirePasswordConfirmationMiddleware lets a request through when the
    | user confirmed their password within timeout_seconds. Otherwise a
    | browser is redirected to 'route' (a URL path your app serves) and a
    | JSON request gets a 423. One user may make max_attempts confirmation
    | attempts within decay_seconds; a correct password resets the count.
    | All numbers must be 1 or more.
    |
    */
    'password_confirmation' => [
        'timeout_seconds' => 10800, // 3 hours
        'route' => '/confirm-password',
        'throttle' => [
            'max_attempts' => 5,
            'decay_seconds' => 60,
        ],
    ],
];
