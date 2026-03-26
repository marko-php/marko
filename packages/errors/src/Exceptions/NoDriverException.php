<?php

declare(strict_types=1);

namespace Marko\Errors\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/errors-advanced',
        'marko/errors-simple',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No error handler driver installed.',
            context: 'Attempted to resolve an error handler interface but no implementation is bound.',
            suggestion: "Install an error handler driver:\n$packageList",
        );
    }
}
