<?php

declare(strict_types=1);

namespace Marko\Layout\Exceptions;

class AmbiguousSortOrderException extends LayoutException
{
    /**
     * @param array<string> $components
     */
    public static function forComponents(string $slot, int $sortOrder, array $components): self
    {
        $componentList = implode(', ', $components);

        return new self(
            message: "Ambiguous sort order $sortOrder for slot '$slot': multiple components share the same order.",
            context: "Components with sort order $sortOrder in slot '$slot': $componentList",
            suggestion: 'Assign unique sort order values to each component within a slot.',
        );
    }
}
