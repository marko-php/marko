<?php

declare(strict_types=1);

namespace Marko\Hashing\Exceptions;

class HasherNotFoundException extends HasherException
{
    public static function forName(
        string $name,
    ): self {
        return new self(
            message: "Hasher '$name' not found",
            context: "Requested hasher: $name",
            suggestion: 'Register the hasher in config/hashing.php or check the hasher name spelling',
        );
    }
}
