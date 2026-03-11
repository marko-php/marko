<?php

declare(strict_types=1);

namespace Marko\Core\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a class as a singleton (shared instance).
     */
    public function singleton(string $id): void;

    /**
     * Register a pre-built instance for an interface or class.
     */
    public function instance(
        string $id,
        object $instance,
    ): void;

    /**
     * Invoke a callable with auto-resolved dependencies.
     */
    public function call(Closure $callable): mixed;
}
