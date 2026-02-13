<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Error;
use Marko\Core\Exceptions\MarkoException;

class RouteException extends MarkoException
{
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
            message: "Failed to load controller file: class or interface '$missingClass' not found",
            context: "While discovering routes in '$filePath'. This usually means a required package is missing.",
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public static function attributeClassNotFound(
        string $controller,
        string $missingClass,
        Error $previous,
    ): self {
        $package = self::inferPackageName($missingClass);
        $suggestion = $package !== null
            ? "Run: composer require $package"
            : "Ensure the class '$missingClass' is available via Composer autoloading";

        return new self(
            message: "Attribute class '$missingClass' not found on controller '$controller'",
            context: 'While instantiating route attributes. This usually means a required package is missing.',
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    public static function ambiguousOverride(
        string $parentClass,
        string $childClass,
        string $method,
    ): self {
        return new self(
            message: "Method '$method' overrides parent but has no #[Route] attribute - unclear if route should be inherited or replaced.",
            context: "Parent: $parentClass::$method(), Child: $childClass::$method()",
            suggestion: "Add #[Route] attribute to explicitly define the route, or use #[InheritRoute] to keep the parent's route configuration.",
        );
    }

    public static function invalidParameter(
        string $path,
        string $parameter,
        string $reason,
    ): self {
        return new self(
            message: "Invalid route parameter '$parameter' in route definition.",
            context: "Path: $path, Error: $reason",
            suggestion: 'Route parameters must use the format {name} or {name:pattern}. Example: {id} or {slug:[a-z-]+}',
        );
    }

    public static function controllerNotFound(
        string $controller,
        string $path,
    ): self {
        return new self(
            message: "Controller class not found: $controller",
            context: "Route path: $path",
            suggestion: 'Verify the class exists and is properly autoloaded. Check the namespace matches the file location.',
        );
    }

    public static function methodNotFound(
        string $controller,
        string $method,
        string $path,
    ): self {
        return new self(
            message: "Method not found: $method",
            context: "Controller: $controller, Route path: $path",
            suggestion: 'Verify the method exists and is public. Route handler methods must be public.',
        );
    }
}
