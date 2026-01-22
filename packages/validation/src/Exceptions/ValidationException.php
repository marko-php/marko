<?php

declare(strict_types=1);

namespace Marko\Validation\Exceptions;

use Exception;
use Marko\Validation\Validation\ValidationErrors;
use Throwable;

class ValidationException extends Exception
{
    public function __construct(
        string $message,
        private readonly ValidationErrors $errors,
        private readonly string $context = '',
        private readonly string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function withErrors(
        ValidationErrors $errors,
    ): self {
        return new self(
            'The given data was invalid.',
            $errors,
            'Validation failed for one or more fields.',
            'Check the errors() method for details on which fields failed.',
        );
    }

    public function errors(): ValidationErrors
    {
        return $this->errors;
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
