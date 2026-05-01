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
            message: 'codewithkyrian/transformers-php is not installed.',
            context: 'The transformers-php package is required for query-time embeddings.',
            suggestion: 'Run `composer require codewithkyrian/transformers-php` to install it.',
        );
    }

    public static function checksumMismatch(string $file, string $expected, string $actual): self
    {
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
}
