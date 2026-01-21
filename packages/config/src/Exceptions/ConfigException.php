<?php

declare(strict_types=1);

namespace Marko\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class ConfigException extends MarkoException
{
    public function __construct(
        string $message,
        string $context = '',
        string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $context,
            $suggestion,
            $code,
            $previous,
        );
    }
}
