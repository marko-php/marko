<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class ComponentNotFoundException extends LayoutException
{
    public static function forComponent(string $name): self
    {
        return new self(
            message: "Component '$name' not found.",
            context: "Attempted to render component '$name' but no matching component was registered.",
            suggestion: 'Verify the component name and ensure it is registered in the layout configuration.',
        );
    }
}
