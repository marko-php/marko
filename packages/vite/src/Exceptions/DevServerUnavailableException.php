<?php

declare(strict_types=1);

namespace Marko\Vite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DevServerUnavailableException extends MarkoException
{
    public static function fromHotFile(
        string $hotFilePath,
    ): self {
        return new self(
            message: "Vite dev server is unavailable because hot file '$hotFilePath' did not contain a valid URL",
            context: "While resolving the Vite development server from '$hotFilePath'",
            suggestion: 'Start the Vite dev server or update the hot file and dev server URL configuration.',
        );
    }

    public static function invalidUrl(
        string $url,
    ): self {
        return new self(
            message: "Configured Vite dev server URL '$url' is invalid",
            context: "While resolving the configured Vite development server URL '$url'",
            suggestion: 'Provide a fully-qualified dev server URL such as http://localhost:5173.',
        );
    }
}
