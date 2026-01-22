<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Exceptions;

use Exception;
use Throwable;

class AdvancedErrorHandlerException extends Exception
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

    public static function handlerNotFound(
        string $handlerName,
    ): self {
        return new self(
            message: "Error handler '$handlerName' not found",
            context: "The requested error handler '$handlerName' is not registered",
            suggestion: 'Ensure the handler is registered via the error handler registry',
        );
    }
}
