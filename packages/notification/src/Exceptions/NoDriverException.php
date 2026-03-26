<?php

declare(strict_types=1);

namespace Marko\Notification\Exceptions;

class NoDriverException extends NotificationException
{
    private const array DRIVER_PACKAGES = [
        'marko/notification-database',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No notification driver installed.',
            context: 'Attempted to resolve a notification interface but no implementation is bound.',
            suggestion: "Install a notification driver:\n$packageList",
        );
    }
}
