<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Marko\Routing\Middleware\MiddlewareInterface;

class ModuleException extends MarkoException
{
    public static function invalidManifest(
        string $moduleName,
        string $reason,
    ): self {
        return new self(
            message: "Invalid module manifest for '$moduleName': $reason",
            context: "While loading module '$moduleName'",
            suggestion: 'Check your module.php file for proper structure and required keys',
        );
    }

    public static function missingDependency(
        string $moduleName,
        string $dependencyName,
    ): self {
        return new self(
            message: "Module '$moduleName' requires '$dependencyName' which is not installed",
            context: "While resolving dependencies for '$moduleName'",
            suggestion: "Install the missing package with: composer require $dependencyName",
        );
    }

    public static function invalidMiddlewareClass(
        string $moduleName,
        string $className,
        string $reason,
    ): self {
        return new self(
            message: "Invalid globalMiddleware entry in module '$moduleName': $reason",
            context: "While resolving global middleware for '$moduleName'",
            suggestion: "Ensure '$className' exists and implements " . MiddlewareInterface::class,
        );
    }

    public static function invalidMiddlewareEntry(
        string $moduleName,
        string $reason,
    ): self {
        return new self(
            message: "Invalid globalMiddleware entry in module '$moduleName': $reason",
            context: "While resolving global middleware for '$moduleName'",
            suggestion: "Each entry must be a class-string or ['class' => SomeMiddleware::class, 'priority' => 10]",
        );
    }
}
