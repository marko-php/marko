<?php

declare(strict_types=1);

namespace Marko\Encryption\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/encryption-openssl',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No encryption driver installed.',
            context: 'Attempted to resolve an encryption interface but no implementation is bound.',
            suggestion: "Install an encryption driver:\n$packageList",
        );
    }
}
