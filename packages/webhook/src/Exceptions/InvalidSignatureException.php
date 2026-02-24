<?php

declare(strict_types=1);

namespace Marko\Webhook\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidSignatureException extends MarkoException
{
    public static function forRequest(): self
    {
        return new self('Invalid webhook signature. The request signature does not match the expected signature.');
    }
}
