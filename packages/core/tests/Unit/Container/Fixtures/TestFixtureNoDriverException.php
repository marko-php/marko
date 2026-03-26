<?php

declare(strict_types=1);

namespace Marko\TestFixture\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NoDriverException extends MarkoException
{
    public static function noDriverInstalled(): self
    {
        return new self(
            message: 'No driver installed for TestFixture',
            context: 'Attempted to resolve TestFixture interface with no driver package installed',
            suggestion: 'Install a TestFixture driver package',
        );
    }
}
