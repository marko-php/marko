<?php

declare(strict_types=1);

namespace Marko\Vite\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ViteManifestException extends MarkoException
{
    public static function notFound(string $manifestPath): self
    {
        return new self(
            sprintf('Vite manifest not found at "%s".', $manifestPath),
            'The production manifest is required when vite.useDevServer is false.',
            'Run "npm run build" to generate the manifest, or set vite.useDevServer=true for development.',
        );
    }

    public static function unreadable(string $manifestPath): self
    {
        return new self(
            sprintf('Vite manifest at "%s" could not be read.', $manifestPath),
            'file_get_contents() returned false for the manifest path.',
            'Check filesystem permissions on the manifest file.',
        );
    }

    public static function invalid(string $manifestPath): self
    {
        return new self(
            sprintf('Vite manifest at "%s" is not valid JSON.', $manifestPath),
            'json_decode() failed or returned a non-object structure.',
            'Re-run "npm run build" to regenerate the manifest.',
        );
    }

    public static function entryNotFound(
        string $entry,
        string $manifestPath,
    ): self {
        return new self(
            sprintf('Vite entry "%s" was not found in manifest "%s".', $entry, $manifestPath),
            'The configured vite.entry does not match any key in the manifest.',
            sprintf('Add "%s" to your vite.config build inputs, or update vite.entry to a known key.', $entry),
        );
    }

    public static function entryFileInvalid(
        string $entry,
        string $manifestPath,
    ): self {
        return new self(
            sprintf('Vite entry "%s" in manifest "%s" has no valid "file" field.', $entry, $manifestPath),
            'The manifest entry is missing or has an empty "file" attribute.',
            'Re-run "npm run build" to regenerate the manifest.',
        );
    }
}
