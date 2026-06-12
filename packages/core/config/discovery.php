<?php

declare(strict_types=1);

$falseValues = ['0', 'false', 'no', 'off', ''];
$rawEnabled = $_ENV['DISCOVERY_CACHE_ENABLED'] ?? null;

return [
    'enabled' => $rawEnabled === null
        ? true
        : !in_array(strtolower((string) $rawEnabled), $falseValues, strict: true),
    'environment' => $_ENV['APP_ENV'] ?? 'production',
    'cache_path' => $_ENV['DISCOVERY_CACHE_PATH'] ?? 'storage/cache/discovery.php',
];
