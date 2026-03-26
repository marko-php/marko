<?php

declare(strict_types=1);

namespace Marko\Translation\Exceptions;

class NoDriverException extends TranslationException
{
    private const array DRIVER_PACKAGES = [
        'marko/translation-file',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No translation driver installed.',
            context: 'Attempted to resolve a translation interface but no implementation is bound.',
            suggestion: "Install a translation driver:\n$packageList",
        );
    }
}
