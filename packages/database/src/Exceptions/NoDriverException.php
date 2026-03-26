<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/database-mysql',
        'marko/database-pgsql',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No database driver installed.',
            context: 'Attempted to resolve a database interface but no implementation is bound.',
            suggestion: "Install a database driver:\n$packageList",
        );
    }
}
