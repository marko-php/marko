<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Base URL for absolute URLs from UrlGeneratorInterface::route(..., absolute: true),
    // e.g. https://example.com. Never derived from the request's Host header.
    'url' => Env::string('APP_URL', ''),

    // Signed URLs (UrlSignerInterface, ValidSignatureMiddleware).
    'signing' => [
        // HMAC key for signed URLs, at least 32 bytes. Required: signing or validating with an
        // empty key throws. Generate one with: php -r "echo bin2hex(random_bytes(32));"
        // Use a dedicated key; do not reuse ENCRYPTION_KEY.
        'key' => Env::string('URL_SIGNING_KEY', ''),
        // Retired keys (comma-separated in the env) that still validate links signed before a
        // rotation. New signatures always use 'key'. Remove a key once its links have expired.
        'previous_keys' => Env::list('URL_SIGNING_PREVIOUS_KEYS', []),
        // Query parameters left out of the signature, so links still validate when an email
        // client or tracker appends them, e.g. ['utm_source', 'utm_medium', 'fbclid'].
        'ignored_parameters' => [],
    ],

    // Hand FileResponse files to the web server instead of streaming them from PHP.
    // Off by default and never auto-detected; only applies to LocalFileSource files.
    'file_offload' => [
        // 'X-Sendfile' (Apache mod_xsendfile, lighttpd), 'X-Accel-Redirect' (nginx), or '' (off).
        'header' => Env::string('ROUTING_FILE_OFFLOAD_HEADER', ''),
        // Local path prefix => server path prefix, e.g. ['/var/www/storage/videos/' => '/protected-videos/'].
        // Empty sends the local path unchanged (X-Sendfile); required for X-Accel-Redirect.
        'prefix_map' => [],
    ],
];
