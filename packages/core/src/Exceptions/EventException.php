<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Error;

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

    public static function classNotFoundDuringDiscovery(
        string $filePath,
        string $missingClass,
        Error $previous,
    ): self {
        $package = self::inferPackageName($missingClass);
        $suggestion = $package !== null
            ? "Run: composer require $package"
            : "Ensure the class '$missingClass' is available via Composer autoloading";

        return new self(
            message: "Failed to load observer file: class or interface '$missingClass' not found",
            context: "While discovering observers in '$filePath'. This usually means a required package is missing.",
            suggestion: $suggestion,
            previous: $previous,
        );
    }
}
