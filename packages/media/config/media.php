<?php

declare(strict_types=1);

return [
    'disk' => 'local',
    'max_file_size' => 10485760,
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
    'url_prefix' => '/storage',
];
