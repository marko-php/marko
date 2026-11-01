<?php

declare(strict_types=1);

namespace Marko\Routing\Discovery;

use Marko\Routing\Http\HttpExceptionResponderInterface;

/**
 * A discovered #[HttpExceptionResponder] class and its priority.
 */
readonly class HttpExceptionResponderDefinition
{
    /**
     * @param class-string<HttpExceptionResponderInterface> $className
     */
    public function __construct(
        public string $className,
        public int $priority,
    ) {}
}
