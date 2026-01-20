<?php

declare(strict_types=1);

namespace Marko\Errors;

use DateTimeImmutable;
use Marko\Core\Exceptions\MarkoException;
use Throwable;

readonly class ErrorReport
{
    private function __construct(
        public string $id,
        public string $message,
        public int $code,
        public Throwable $throwable,
        public array $trace,
        public string $file,
        public int $line,
        public Severity $severity,
        public DateTimeImmutable $timestamp,
        public string $context,
        public string $suggestion,
        public ?Throwable $previous,
    ) {}

    public static function fromThrowable(
        Throwable $throwable,
        Severity $severity,
    ): self {
        return new self(
            id: bin2hex(random_bytes(16)),
            message: $throwable->getMessage(),
            code: $throwable->getCode(),
            throwable: $throwable,
            trace: $throwable->getTrace(),
            file: $throwable->getFile(),
            line: $throwable->getLine(),
            severity: $severity,
            timestamp: new DateTimeImmutable(),
            context: $throwable instanceof MarkoException ? $throwable->getContext() : '',
            suggestion: $throwable instanceof MarkoException ? $throwable->getSuggestion() : '',
            previous: $throwable->getPrevious(),
        );
    }
}
