<?php

declare(strict_types=1);

namespace Marko\Http\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/http-guzzle',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No HTTP client driver installed.',
            context: 'Attempted to resolve an HTTP client interface but no implementation is bound.',
            suggestion: "Install an HTTP client driver:\n$packageList",
        );
    }
}
