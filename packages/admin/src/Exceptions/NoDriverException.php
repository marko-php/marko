<?php

declare(strict_types=1);

namespace Marko\Admin\Exceptions;

class NoDriverException extends AdminException
{
    private const array DRIVER_PACKAGES = [
        'marko/admin-api',
        'marko/admin-auth',
        'marko/admin-panel',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No admin driver installed.',
            context: 'Attempted to resolve an admin interface but no implementation is bound.',
            suggestion: "Install an admin driver:\n$packageList",
        );
    }
}
