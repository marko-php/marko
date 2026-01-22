<?php

declare(strict_types=1);

namespace Marko\Auth\Exceptions;

use Exception;
use Throwable;

class AuthException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $context = '',
        private readonly string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }
}
