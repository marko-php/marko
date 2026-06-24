<?php

declare(strict_types=1);

namespace Marko\DocsVec\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class VecRuntimeException extends MarkoException
{
    public static function sqliteVecNotLoaded(string $reason): self
    {
        return new self(
            message: 'sqlite-vec extension could not be loaded: ' . $reason,
            context: 'The sqlite-vec extension is required for vector search functionality.',
            suggestion: 'Install sqlite-vec from https://github.com/asg017/sqlite-vec and ensure the shared library is accessible.',
        );
    }

    public static function modelMissing(string $path): self
    {
        return new self(
            message: 'ONNX model not found at: ' . $path,
            context: 'The bge-small-en-v1.5 ONNX model is required for generating embeddings.',
            suggestion: 'Run `marko docs-vec:download-model` to fetch the model weights.',
        );
    }

    public static function transformersNotInstalled(): self
    {
        return new self(
            message: 'codewithkyrian/transformers is not installed.',
            context: 'The transformers-php package is required for query-time embeddings.',
            suggestion: 'Run `composer require codewithkyrian/transformers` to install it.',
        );
    }

    public static function checksumMismatch(
        string $file,
        string $expected,
        string $actual,
    ): self {
        return new self(
            message: 'SHA-256 checksum mismatch for file: ' . $file,
            context: sprintf('Expected: %s, Got: %s', $expected, $actual),
            suggestion: 'The downloaded file may be corrupted. Delete it and re-run `marko docs-vec:download-model`.',
        );
    }

    public static function emptyQuery(): self
    {
        return new self(
            message: 'Cannot embed empty query string',
            context: 'While preparing query for vector search',
            suggestion: 'Provide a non-empty search query',
        );
    }

    public static function downloadFailed(string $url): self
    {
        return new self(
            message: 'Failed to download model file from: ' . $url,
            context: 'The HTTP request for a model file returned no data.',
            suggestion: 'Check your network connection. If you are behind a firewall or mirror the files, pass --base-url=<your-mirror> to `marko docs-vec:download-model`.',
        );
    }

    public static function unsupportedPlatform(string $platform): self
    {
        return new self(
            message: 'No prebuilt sqlite-vec binary for platform: ' . $platform,
            context: 'docs-vec ships pinned sqlite-vec builds for macOS, Linux, and Windows (x86_64 / arm64).',
            suggestion: 'Build sqlite-vec from https://github.com/asg017/sqlite-vec and place vec0.{so,dylib,dll} in resources/sqlite-vec/, or run docs-vec FTS5-only (no extension needed).',
        );
    }

    public static function extractionFailed(
        string $archive,
        string $reason,
    ): self {
        return new self(
            message: 'Failed to extract the sqlite-vec binary from: ' . $archive,
            context: $reason,
            suggestion: 'Delete the archive and re-run `marko docs-vec:download-extension`. Ensure the Phar extension is enabled.',
        );
    }
}
