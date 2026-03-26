<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/cache-array',
        'marko/cache-file',
        'marko/cache-redis',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No cache driver installed.',
            context: 'Attempted to resolve a cache interface but no implementation is bound.',
            suggestion: "Install a cache driver:\n$packageList",
        );
    }
}
