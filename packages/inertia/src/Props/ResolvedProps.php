<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

readonly class ResolvedProps
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public array $props,
        public array $metadata = [],
    ) {}
}
