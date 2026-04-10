<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class DuplicateComponentException extends LayoutException
{
    public static function forComponent(string $name, string $moduleA, string $moduleB): self
    {
        return new self(
            message: "Component '$name' is registered in multiple modules.",
            context: "Component '$name' is registered in both '$moduleA' and '$moduleB'.",
            suggestion: 'Use a Preference in a higher-priority module to resolve the conflict, or rename one of the components.',
        );
    }

    public static function duplicate(string $className): self
    {
        return new self(
            message: "Component '$className' is already registered.",
            context: "Attempted to add '$className' but it already exists in the collection.",
            suggestion: 'Remove the existing component before adding it again, or use move() to change its slot.',
        );
    }
}
