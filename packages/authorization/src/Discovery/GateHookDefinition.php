<?php

declare(strict_types=1);

namespace Marko\Authorization\Discovery;

use Marko\Authorization\Contracts\GateHookInterface;

/**
 * A discovered #[GateHook] class and its priority.
 */
readonly class GateHookDefinition
{
    /**
     * @param class-string<GateHookInterface> $className
     */
    public function __construct(
        public string $className,
        public int $priority,
    ) {}
}
