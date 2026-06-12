<?php

declare(strict_types=1);

namespace Marko\Cors\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CorsException extends MarkoException
{
    public static function wildcardWithCredentials(): self
    {
        return new self(
            message: 'CORS misconfiguration: wildcard origin (*) cannot be used with credentials.',
            context: 'While processing a CORS request with allowed_origins=[*] and supports_credentials=true',
            suggestion: 'Either restrict allowed_origins to explicit origins, or set supports_credentials to false',
        );
    }
}
