<?php

declare(strict_types=1);

namespace Marko\Filesystem\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/filesystem-local',
        'marko/filesystem-s3',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No filesystem driver installed.',
            context: 'Attempted to resolve a filesystem interface but no implementation is bound.',
            suggestion: "Install a filesystem driver:\n$packageList",
        );
    }
}
