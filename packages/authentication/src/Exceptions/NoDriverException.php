<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    private const array DRIVER_PACKAGES = [
        'marko/authentication-token',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No authentication driver installed.',
            context: 'Attempted to resolve an authentication interface but no implementation is bound.',
            suggestion: "Install an authentication driver:\n$packageList",
        );
    }
}
