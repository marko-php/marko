<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Base URL for absolute URLs from UrlGeneratorInterface::route(..., absolute: true),
    // e.g. https://example.com. Never derived from the request's Host header.
    'url' => Env::string('APP_URL', ''),

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
