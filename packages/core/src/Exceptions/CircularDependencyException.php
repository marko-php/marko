<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends MarkoException implements ContainerExceptionInterface
{
    /**
     * @param string[] $chain
     */
    public static function forChain(
        array $chain,
    ): self {
        $chainPath = implode(' -> ', $chain);

        return new self(
            message: "Circular dependency detected: $chainPath",
            context: 'While resolving constructor dependencies for ' . ($chain[0] ?? ''),
            suggestion: 'Remove the circular reference in the constructor dependency chain',
        );
    }

    /**
     * @param string[] $chain
     */
    public static function detected(
        array $chain,
    ): self {
        $chainPath = implode(' -> ', $chain);

        return new self(
            message: "Circular dependency detected: $chainPath",
            context: 'While resolving module dependency chain',
            suggestion: 'Review module dependencies to remove the circular reference. One module should not depend on another that depends back on it',
        );
    }
}
