<?php

declare(strict_types=1);

namespace Marko\Mail\Exception;

use Marko\Core\Exceptions\MarkoException;

class MailException extends MarkoException
{
    public static function noDriverInstalled(): self
    {
        return new self(
            message: 'No mail driver installed.',
            context: 'Attempted to resolve MailerInterface but no implementation is bound.',
            suggestion: 'Install a mail driver package: composer require marko/mail-smtp',
        );
    }

    public static function configFileNotFound(
        string $path,
    ): self {
        return new self(
            message: 'Mail configuration file not found.',
            context: "Expected configuration file at: $path",
            suggestion: 'Create a mail.php configuration file or publish the default config.',
        );
    }
}
