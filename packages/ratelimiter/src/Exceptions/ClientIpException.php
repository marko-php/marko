<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class ClientIpException extends MarkoException
{
    public static function missingRemoteAddr(): self
    {
        return new self(
            message: 'Cannot resolve client IP: REMOTE_ADDR is absent',
            context: 'While resolving client IP for rate limiting',
            suggestion: 'Ensure the request has a REMOTE_ADDR set (this is unavailable in CLI contexts)',
        );
    }
}
