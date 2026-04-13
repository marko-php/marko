<?php

declare(strict_types=1);

namespace Marko\Vite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class EntrypointNotFoundException extends MarkoException
{
    public static function forEntrypoint(
        string $entrypoint,
        string $manifestPath,
    ): self {
        return new self(
            message: "Vite entrypoint '$entrypoint' was not found in manifest '$manifestPath'",
            context: "While resolving Vite entrypoint '$entrypoint'",
            suggestion: 'Ensure the entrypoint exists in your Vite build output and matches the path used in templates.',
        );
    }
}
