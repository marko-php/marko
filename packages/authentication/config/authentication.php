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
    | Each guard has a driver and a provider.
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
    | Providers define how users are retrieved from your database or
    | other storage mechanisms.
    |
    */
    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => 'users',
        ],
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
    | Configuration for "remember me" functionality. Expiration is in minutes.
    |
    */
    'remember' => [
        'expiration' => 43200, // 30 days
        'cookie' => 'remember_token',
    ],
];
