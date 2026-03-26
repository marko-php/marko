<?php

declare(strict_types=1);

namespace Marko\Log\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/log-file',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No log driver installed.',
            context: 'Attempted to resolve a logger interface but no implementation is bound.',
            suggestion: "Install a log driver:\n$packageList",
        );
    }
}
