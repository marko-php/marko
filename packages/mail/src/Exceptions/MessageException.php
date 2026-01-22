<?php

declare(strict_types=1);

namespace Marko\Mail\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class MessageException extends MarkoException
{
    public static function invalidEmailAddress(
        string $email,
    ): self {
        return new self(
            message: "Invalid email address: '$email'",
            context: "While validating email address '$email'",
            suggestion: 'Provide a valid email address in the format user@domain.com',
        );
    }

    public static function attachmentNotFound(
        string $path,
    ): self {
        return new self(
            message: "Attachment file not found: '$path'",
            context: "Attempted to attach file: $path",
            suggestion: 'Verify the file path exists and is readable.',
        );
    }
}
