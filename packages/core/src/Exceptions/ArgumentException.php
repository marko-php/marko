<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class ArgumentException extends MarkoException implements ContainerExceptionInterface
{
    public static function invalidShape(
        string $module,
        string $problem,
    ): self {
        return new self(
            message: "Invalid 'arguments' in module '$module': $problem",
            context: "While reading the 'arguments' key of the module.php for '$module'",
            suggestion: "Declare 'arguments' as [ConcreteClass::class => ['parameterName' => value]]",
        );
    }

    public static function reboundClass(
        string $class,
        string $boundTo,
    ): self {
        return new self(
            message: "Class '$class' is bound to '$boundTo', so it is never built and its 'arguments' never apply",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Key the arguments by '$boundTo', the class that is actually built",
        );
    }

    public static function interfaceKey(
        string $interface,
    ): self {
        return new self(
            message: "Arguments cannot be keyed by the interface '$interface'",
            context: "While applying the 'arguments' entry for '$interface'",
            suggestion: "Key the arguments by the concrete class that implements '$interface' instead of the interface",
        );
    }

    public static function abstractKey(
        string $class,
    ): self {
        return new self(
            message: "Arguments cannot be keyed by the abstract class '$class', which is never built and has no Preference replacement",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Key the arguments by the concrete class that extends '$class', or add a Preference that replaces '$class'",
        );
    }

    /**
     * @param array<int, string> $modules Module names (with priorities) that closure-bind the class, when known
     */
    public static function closureBound(
        string $class,
        array $modules = [],
    ): self {
        $boundBy = $modules === [] ? '' : ' by ' . implode(', ', $modules);

        return new self(
            message: "Class '$class' is bound to a closure$boundBy, so its constructor never receives 'arguments'",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Remove the 'arguments' entry for '$class' and pass the values inside the closure."
                . ' A higher-priority module may closure-bind the class to take over construction',
        );
    }

    public static function noConstructor(
        string $class,
    ): self {
        return new self(
            message: "Class '$class' has no constructor, so 'arguments' cannot be applied to it",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Remove the 'arguments' entry for '$class', or add a constructor that takes the values",
        );
    }

    public static function objectInstance(
        string $class,
        string $parameter,
        string $instanceClass,
        ?string $enumCase = null,
    ): self {
        $short = ltrim((string) strrchr('\\' . $instanceClass, '\\'), '\\');
        $factory = $enumCase === null ? "new $short(...)" : "$short::$enumCase";

        return new self(
            message: "Argument for '\$$parameter' of '$class' is an object instance of '$instanceClass', which 'arguments' does not accept",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Give a class name, or wrap the value in a closure factory: `fn () => $factory`",
        );
    }

    public static function variadicParameter(
        string $class,
        string $parameter,
    ): self {
        return new self(
            message: "Cannot set the variadic parameter '\$$parameter' of '$class' through 'arguments'",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Bind '$class' to a closure that passes the variadic values explicitly",
        );
    }

    public static function ambiguousType(
        string $class,
        string $parameter,
        string $type,
    ): self {
        return new self(
            message: "Cannot tell how to check the argument for '\$$parameter' of '$class': its type '$type' is ambiguous",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Use a closure factory for '\$$parameter', e.g. `fn () => ...`, so the value is built explicitly",
        );
    }

    public static function typeMismatch(
        string $class,
        string $parameter,
        string $expectedType,
        string $actualType,
    ): self {
        return new self(
            message: "Argument for '\$$parameter' of '$class' must be of type '$expectedType', '$actualType' given",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Change the value to a '$expectedType'",
        );
    }

    public static function classDoesNotSatisfyType(
        string $class,
        string $parameter,
        string $expectedType,
        string $givenClass,
    ): self {
        return new self(
            message: "Argument for '\$$parameter' of '$class' must satisfy '$expectedType', but '$givenClass' does not",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Give a class that is or implements '$expectedType'",
        );
    }

    /**
     * @param array<int, string> $availableParameters
     */
    public static function unknownInheritedParameter(
        string $replacement,
        string $parameter,
        string $replacedClass,
        array $availableParameters,
    ): self {
        $available = implode(', ', $availableParameters);

        return new self(
            message: "'$replacement' replaces '$replacedClass' but has no constructor parameter named '\$$parameter', which the arguments for '$replacedClass' set",
            context: "While inheriting the 'arguments' entry of '$replacedClass' through a Preference",
            suggestion: "Rename the argument to one of the constructor parameters of '$replacement': $available",
        );
    }

    /**
     * @param array<int, string> $availableParameters
     */
    public static function unknownParameter(
        string $class,
        string $parameter,
        array $availableParameters,
    ): self {
        $available = implode(', ', $availableParameters);

        return new self(
            message: "Class '$class' has no constructor parameter named '\$$parameter'",
            context: "While applying the 'arguments' entry for '$class'",
            suggestion: "Use one of the constructor parameter names: $available",
        );
    }
}
