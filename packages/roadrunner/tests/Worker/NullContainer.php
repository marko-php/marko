<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Closure;
use Marko\Core\Container\ContainerInterface;
use RuntimeException;

/**
 * A {@see ContainerInterface} stub for {@see WorkerRequestHandler} tests
 * that never legitimately need to resolve anything — the fixture routes
 * used in those tests never match, so the Router never reaches the
 * container. Any access is a test setup mistake and throws loudly.
 */
class NullContainer implements ContainerInterface
{
    public function get(string $id): never
    {
        throw new RuntimeException("Unexpected container access for '$id' in test.");
    }

    public function has(string $id): bool
    {
        return false;
    }

    public function singleton(string $id): void {}

    public function instance(
        string $id,
        object $instance,
    ): void {}

    public function call(Closure $callable): never
    {
        throw new RuntimeException('Unexpected container call() in test.');
    }

    /**
     * Unlike get() and call(), this is a query rather than a resolution: it
     * never constructs anything, so an empty result is the honest answer for
     * a container that has resolved nothing. A worker's per-request reset
     * loop calls it, and must be able to no-op cleanly.
     *
     * @return array<string, object>
     */
    public function resolvedInstances(?string $interface = null): array
    {
        return [];
    }
}
