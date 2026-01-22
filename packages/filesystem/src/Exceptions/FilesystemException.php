<?php

declare(strict_types=1);

namespace Marko\Filesystem\Exceptions;

use Exception;
use Throwable;

class FilesystemException extends Exception
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

    public static function fromThrowable(
        Throwable $e,
    ): self {
        return new self(
            message: $e->getMessage(),
            context: 'Original exception: ' . $e::class,
            suggestion: '',
            code: $e->getCode(),
            previous: $e,
        );
    }
}
