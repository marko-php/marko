<?php

declare(strict_types=1);

namespace Marko\Session\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/session-database',
        'marko/session-file',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No session driver installed.',
            context: 'Attempted to resolve a session interface but no implementation is bound.',
            suggestion: "Install a session driver:\n$packageList",
        );
    }
}
