<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}
