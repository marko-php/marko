<?php

declare(strict_types=1);

namespace Marko\Authorization\Attributes;

use Attribute;

/**
 * Registers a GateHookInterface implementation with the Gate.
 *
 * Hooks are discovered at boot and run highest priority first, like #[Observer].
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class GateHook
{
    public function __construct(
        public int $priority = 0,
    ) {}
}
