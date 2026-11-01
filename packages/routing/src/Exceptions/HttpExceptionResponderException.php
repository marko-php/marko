<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * An #[HttpExceptionResponder] class is declared incorrectly.
 */
class HttpExceptionResponderException extends MarkoException
{
    public static function notAResponder(
        string $className,
    ): self {
        return new self(
            message: "HTTP exception responder '$className' does not implement HttpExceptionResponderInterface",
            context: "'$className' is marked with #[HttpExceptionResponder], so the ExceptionRenderer would call its respond() method",
            suggestion: "Implement Marko\\Routing\\Http\\HttpExceptionResponderInterface on '$className', or remove the #[HttpExceptionResponder] attribute",
        );
    }
}
