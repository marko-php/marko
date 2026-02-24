<?php

declare(strict_types=1);

return [
    'key' => $_ENV['ENCRYPTION_KEY'] ?? '',
    'cipher' => $_ENV['ENCRYPTION_CIPHER'] ?? 'aes-256-gcm',
];
