<?php

declare(strict_types=1);

namespace Marko\Queue\Exceptions;

class NoDriverException extends QueueException
{
    private const array DRIVER_PACKAGES = [
        'marko/queue-database',
        'marko/queue-rabbitmq',
        'marko/queue-sync',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No queue driver installed.',
            context: 'Attempted to resolve a queue interface but no implementation is bound.',
            suggestion: "Install a queue driver:\n$packageList",
        );
    }
}
