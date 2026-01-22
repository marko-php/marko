<?php

declare(strict_types=1);

namespace Marko\Mail\Exception;

class MessageException extends MailException
{
    public static function invalidEmailAddress(
        string $address,
    ): self {
        return new self(
            message: 'Invalid email address.',
            context: "The address \"$address\" is not a valid email address.",
            suggestion: 'Provide a valid email address in the format: name@example.com',
        );
    }

    public static function attachmentNotFound(
        string $path,
    ): self {
        return new self(
            message: 'Attachment file not found.',
            context: "Could not find file at: $path",
            suggestion: 'Verify the file path is correct and the file exists.',
        );
    }

    public static function noRecipients(): self
    {
        return new self(
            message: 'No recipients specified.',
            context: 'Message has no To, Cc, or Bcc recipients.',
            suggestion: 'Add at least one recipient using to(), cc(), or bcc().',
        );
    }
}
