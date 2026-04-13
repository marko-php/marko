<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Marko\\Inertia\\React\\' => __DIR__ . '/../src/',
        'Marko\\Inertia\\React\\Tests\\' => __DIR__ . '/',
    ];

    foreach ($prefixes as $prefix => $basePath) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $basePath . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
});
