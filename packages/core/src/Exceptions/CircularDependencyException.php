<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class CircularDependencyException extends MarkoException
{
    /**
     * @param string[] $chain
     */
    public static function detected(array $chain): self
    {
        $chainPath = implode(' -> ', $chain);

        return new self(
            message: "Circular dependency detected: $chainPath",
            context: 'While resolving module dependency chain',
            suggestion: 'Review module dependencies to remove the circular reference. One module should not depend on another that depends back on it',
        );
    }
}
