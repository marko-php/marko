<?php

declare(strict_types=1);

namespace Marko\Mail\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/mail-log',
        'marko/mail-smtp',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No mail driver installed.',
            context: 'Attempted to resolve a mail interface but no implementation is bound.',
            suggestion: "Install a mail driver:\n$packageList",
        );
    }
}
