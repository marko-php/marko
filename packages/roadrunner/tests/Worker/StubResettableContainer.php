<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Closure;
use Marko\Core\Container\ContainerInterface;
use RuntimeException;

/**
 * A {@see ContainerInterface} stub exposing a fixed, caller-supplied
 * resolvedInstances() set while get()/call() throw loudly and count every
 * call. Unlike {@see NullContainer}, resolvedInstances() is configurable —
 * lets the reset-lifecycle tests hand the worker exactly the "already
 * resolved" services under test without a real container, while proving
 * the reset loop never reaches for get()/call() to build what it resets.
 */
class StubResettableContainer implements ContainerInterface
{
    public private(set) int $getCallCount = 0;

    /**
     * @param array<string, object> $resolved
     */
    public function __construct(
        private readonly array $resolved = [],
    ) {}

    /**
     * @throws RuntimeException
     */
    public function get(string $id): never
    {
        $this->getCallCount++;

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

    /**
     * @throws RuntimeException
     */
    public function call(Closure $callable): never
    {
        throw new RuntimeException('Unexpected container call() in test.');
    }

    /**
     * @return array<string, object>
     */
    public function resolvedInstances(?string $interface = null): array
    {
        if ($interface === null) {
            return $this->resolved;
        }

        return array_filter(
            $this->resolved,
            static fn (object $instance): bool => $instance instanceof $interface,
        );
    }
}
