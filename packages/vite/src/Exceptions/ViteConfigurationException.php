<?php

declare(strict_types=1);

namespace Marko\Vite\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class ViteConfigurationException extends MarkoException
{
    public static function missingOrInvalid(
        string $key,
        Throwable $previous,
    ): self {
        return new self(
            sprintf('Vite configuration key "%s" is missing or invalid.', $key),
            $previous->getMessage(),
            'Publish or update config/vite.php with the expected Vite configuration keys.',
            previous: $previous,
        );
    }

    public static function empty(
        string $key,
        string $suggestion,
    ): self {
        return new self(
            sprintf('Vite configuration key "%s" must not be empty.', $key),
            sprintf('The "%s" value resolved to an empty string.', $key),
            $suggestion,
        );
    }
}
