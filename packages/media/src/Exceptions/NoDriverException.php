<?php

declare(strict_types=1);

namespace Marko\Media\Exceptions;

class NoDriverException extends MediaException
{
    private const array DRIVER_PACKAGES = [
        'marko/media-gd',
        'marko/media-imagick',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No media driver installed.',
            context: 'Attempted to resolve a media processing interface but no implementation is bound.',
            suggestion: "Install a media driver:\n$packageList",
        );
    }
}
