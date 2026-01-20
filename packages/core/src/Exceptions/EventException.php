<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class EventException extends MarkoException
{
    public static function missingHandleMethod(
        string $observerClass,
    ): self {
        return new self(
            message: "Observer '$observerClass' must have a handle method",
            context: "While validating observer '$observerClass'",
            suggestion: 'Add a public handle() method that accepts the event as its first parameter',
        );
    }
}
