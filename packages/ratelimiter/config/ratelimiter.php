<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | A list of IP addresses (IPv4 or IPv6) that are trusted reverse proxies.
    | When REMOTE_ADDR matches a trusted proxy, the X-Forwarded-For header is
    | honored to resolve the real client IP. The right-most untrusted hop in
    | the XFF chain is used to prevent header-spoofing attacks.
    |
    | Default: [] (no proxies trusted — REMOTE_ADDR is always used directly)
    |
    */
    'trusted_proxies' => [],
];
