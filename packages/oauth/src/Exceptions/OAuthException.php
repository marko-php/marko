<?php

declare(strict_types=1);

namespace Marko\OAuth\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class OAuthException extends MarkoException
{
    public static function keyFileExists(
        string $path,
    ): self {
        return new self(
            message: 'OAuth key file already exists.',
            context: "Refusing to overwrite '$path'.",
            suggestion: 'Run oauth:keys --force if you intentionally want to replace the existing key pair.',
        );
    }

    public static function keyGenerationFailed(
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: 'Failed to generate OAuth signing keys.',
            context: 'OpenSSL did not return a usable key pair.',
            suggestion: 'Verify the OpenSSL extension is installed and available to PHP.',
            previous: $previous,
        );
    }

    public static function keyWriteFailed(
        string $path,
    ): self {
        return new self(
            message: 'Failed to write OAuth key file.',
            context: "Could not write key material to '$path'.",
            suggestion: 'Verify the directory exists and is writable by the current PHP process.',
        );
    }

    public static function keyDirectoryFailed(
        string $path,
    ): self {
        return new self(
            message: 'Failed to create OAuth key directory.',
            context: "Could not create directory '$path'.",
            suggestion: 'Verify the parent directory is writable by the current PHP process.',
        );
    }
}
