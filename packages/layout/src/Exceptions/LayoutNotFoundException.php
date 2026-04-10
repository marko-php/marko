<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class LayoutNotFoundException extends LayoutException
{
    public static function forLayout(string $name): self
    {
        return new self(
            message: "Layout '$name' not found.",
            context: "Attempted to use layout '$name' but no matching layout was registered.",
            suggestion: 'Verify the layout name and ensure it is registered in the layout configuration.',
        );
    }
}
