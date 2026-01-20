<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class BindingException extends MarkoException implements ContainerExceptionInterface
{
    public static function noImplementation(
        string $interface,
    ): self {
        return new self(
            message: "No implementation bound for interface: $interface",
            context: "Attempted to resolve '$interface' from the container",
            suggestion: "Register a binding in your module.php: 'bindings' => ['$interface' => YourImplementation::class]",
        );
    }

    public static function unresolvableParameter(
        string $parameter,
        string $class,
    ): self {
        return new self(
            message: "Cannot resolve parameter '\$$parameter' in class '$class'",
            context: 'Parameter has no type declaration or is a built-in type that cannot be autowired',
            suggestion: "Add a class or interface type hint, or register a factory for '$class'",
        );
    }
}
