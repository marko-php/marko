<?php

declare(strict_types=1);

namespace Marko\Inertia\Exceptions;

class ComponentNotFoundException extends InertiaException
{
    /**
     * @param array<string> $searchedPaths
     */
    public static function forComponent(
        string $component,
        array $searchedPaths,
    ): self {
        return new self(
            message: "Inertia component '$component' not found.",
            context: "Searched paths:\n" . implode("\n", $searchedPaths),
            suggestion: 'Verify the component name and ensure it exists in one of the configured Inertia page directories.',
        );
    }
}
