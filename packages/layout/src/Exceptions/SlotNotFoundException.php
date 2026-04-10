<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class SlotNotFoundException extends LayoutException
{
    public static function forSlot(string $slot, string $layout): self
    {
        return new self(
            message: "Slot '$slot' not found in layout '$layout'.",
            context: "Attempted to fill slot '$slot' in layout '$layout' but the slot is not defined.",
            suggestion: "Verify the slot name and ensure it is defined in the '$layout' layout template.",
        );
    }
}
