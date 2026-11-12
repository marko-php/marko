<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
use Marko\Core\Exceptions\ArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;

class ArgumentResolver
{
    /**
     * @param ReflectionClass<object> $class
     * @param array<string, mixed> $arguments
     * @throws ArgumentException
     */
    public function validate(
        ReflectionClass $class,
        array $arguments,
        ?string $inheritedFrom = null,
    ): void {
        $constructor = $class->getConstructor();

        if ($constructor === null) {
            throw ArgumentException::noConstructor($class->getName());
        }

        $parameters = [];

        foreach ($constructor->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        foreach ($arguments as $name => $value) {
            if (!isset($parameters[$name])) {
                if ($inheritedFrom !== null) {
                    throw ArgumentException::unknownInheritedParameter(
                        $class->getName(),
                        (string) $name,
                        $inheritedFrom,
                        array_keys($parameters),
                    );
                }

                throw ArgumentException::unknownParameter($class->getName(), (string) $name, array_keys($parameters));
            }

            $this->validateValue($parameters[$name], $value, $class->getName());
        }
    }

    /**
     * @throws ArgumentException
     */
    public function validateValue(
        ReflectionParameter $parameter,
        mixed $value,
        string $class,
    ): void {
        if ($parameter->isVariadic()) {
            throw ArgumentException::variadicParameter($class, $parameter->getName());
        }

        if ($value instanceof Closure) {
            return;
        }

        if ($value === null && $parameter->allowsNull()) {
            return;
        }

        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            throw ArgumentException::ambiguousType($class, $parameter->getName(), (string) $type);
        }

        if ($type->isBuiltin()) {
            $this->validateLiteral($parameter, $type->getName(), $value, $class);

            return;
        }

        $this->validateClassName($parameter, $type->getName(), $value, $class);
    }

    /**
     * @throws ArgumentException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function resolveValue(
        ReflectionParameter $parameter,
        mixed $value,
        string $class,
        ContainerInterface $container,
    ): mixed {
        $this->validateValue($parameter, $value, $class);

        $type = $parameter->getType();

        if ($value instanceof Closure) {
            return $this->checkFactoryResult($parameter, $value($container), $class);
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_string($value)) {
            return $container->get($value);
        }

        if ($type instanceof ReflectionNamedType && $type->getName() === 'float' && is_int($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * @throws ArgumentException
     */
    private function checkFactoryResult(
        ReflectionParameter $parameter,
        mixed $result,
        string $class,
    ): mixed {
        $type = $parameter->getType();

        if ($type !== null && !$this->matchesType($type, $result, $parameter)) {
            throw ArgumentException::typeMismatch(
                $class,
                $parameter->getName(),
                (string) $type,
                get_debug_type($result),
            );
        }

        return $result;
    }

    private function matchesType(
        ReflectionType $type,
        mixed $value,
        ReflectionParameter $parameter,
    ): bool {
        if ($type instanceof ReflectionUnionType) {
            return array_any(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->matchesType($member, $value, $parameter),
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            return array_all(
                $type->getTypes(),
                fn (ReflectionType $member): bool => $this->matchesType($member, $value, $parameter),
            );
        }

        if (!$type instanceof ReflectionNamedType) {
            return false;
        }

        if ($value === null && $type->allowsNull()) {
            return true;
        }

        return $this->matchesNamedType($type, $value, $parameter);
    }

    private function matchesNamedType(
        ReflectionNamedType $type,
        mixed $value,
        ReflectionParameter $parameter,
    ): bool {
        $name = $type->getName();

        if (!$type->isBuiltin()) {
            $target = in_array($name, ['self', 'static'], true) ? $parameter->getDeclaringClass()?->getName() : $name;

            return $target !== null && $value instanceof $target;
        }

        return match ($name) {
            'mixed' => true,
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'false' => $value === false,
            'true' => $value === true,
            'null', 'void' => $value === null,
            'array' => is_array($value),
            'object' => is_object($value),
            'iterable' => is_iterable($value),
            'callable' => is_callable($value),
            default => false,
        };
    }

    /**
     * @throws ArgumentException
     */
    private function validateClassName(
        ReflectionParameter $parameter,
        string $type,
        mixed $value,
        string $class,
    ): void {
        if ($value instanceof UnitEnum && $value instanceof $type) {
            return;
        }

        if (is_object($value)) {
            throw ArgumentException::objectInstance(
                $class,
                $parameter->getName(),
                $value::class,
                $value instanceof UnitEnum ? $value->name : null,
            );
        }

        if (!is_string($value)) {
            throw ArgumentException::typeMismatch($class, $parameter->getName(), $type, get_debug_type($value));
        }

        if (!is_a($value, $type, true)) {
            throw ArgumentException::classDoesNotSatisfyType($class, $parameter->getName(), $type, $value);
        }
    }

    /**
     * @throws ArgumentException
     */
    private function validateLiteral(
        ReflectionParameter $parameter,
        string $type,
        mixed $value,
        string $class,
    ): void {
        $matches = match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array', 'iterable' => is_array($value),
            default => throw ArgumentException::ambiguousType($class, $parameter->getName(), $type),
        };

        if (!$matches) {
            throw ArgumentException::typeMismatch($class, $parameter->getName(), $type, get_debug_type($value));
        }
    }
}
