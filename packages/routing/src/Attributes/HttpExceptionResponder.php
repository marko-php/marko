<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

use Attribute;

/**
 * Registers an HttpExceptionResponderInterface implementation with the
 * ExceptionRenderer.
 *
 * Responders are discovered at boot and asked highest priority first, like
 * #[Observer]. The first one that returns a Response handles the exception.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class HttpExceptionResponder
{
    public function __construct(
        public int $priority = 0,
    ) {}
}
