<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class NoDriverException extends ViewException
{
    private const array DRIVER_PACKAGES = [
        'marko/view-latte',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No view driver installed.',
            context: 'Attempted to resolve ViewInterface but no implementation is bound.',
            suggestion: "Install a view driver:\n$packageList",
        );
    }
}
