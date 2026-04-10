<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class CircularSlotException extends LayoutException
{
    /**
     * @param array<string> $chain
     */
    public static function forSlot(string $slot, array $chain): self
    {
        $chainPath = implode(' -> ', $chain);

        return new self(
            message: "Circular slot reference detected for slot '$slot'.",
            context: "Slot resolution chain: $chainPath",
            suggestion: 'Review your layout and slot definitions to remove the circular reference.',
        );
    }
}
