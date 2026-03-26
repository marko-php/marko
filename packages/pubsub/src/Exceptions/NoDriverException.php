<?php

declare(strict_types=1);

namespace Marko\PubSub\Exceptions;

class NoDriverException extends PubSubException
{
    private const array DRIVER_PACKAGES = [
        'marko/pubsub-pgsql',
        'marko/pubsub-redis',
    ];

    public static function noDriverInstalled(): self
    {
        $packageList = implode("\n", array_map(
            fn (string $pkg) => "- `composer require $pkg`",
            self::DRIVER_PACKAGES,
        ));

        return new self(
            message: 'No pub/sub driver installed.',
            context: 'Attempted to resolve a pub/sub interface but no implementation is bound.',
            suggestion: "Install a pub/sub driver:\n$packageList",
        );
    }
}
