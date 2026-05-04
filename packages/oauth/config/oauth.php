<?php

declare(strict_types=1);

return [
    'routes' => [
        'enabled' => true,
        'prefix' => '/oauth',
        'management' => false,
    ],

    'keys' => [
        'private' => 'storage/oauth/private.key',
        'public' => 'storage/oauth/public.key',
        'passphrase' => null,
    ],

    'tokens' => [
        'access_token_ttl' => 'PT1H',
        'refresh_token_ttl' => 'P30D',
        'auth_code_ttl' => 'PT10M',
        'check_revocation' => true,
    ],

    'refresh_tokens' => [
        'rotate' => true,
        'reuse_detection' => true,
    ],

    'consent' => [
        'remember' => true,
        'ttl' => 'P1Y',
    ],

    'scopes' => [
    ],

    'default_scopes' => [
    ],
];
