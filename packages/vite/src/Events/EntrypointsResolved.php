<?php

declare(strict_types=1);

namespace Marko\Vite\Events;

use Marko\Core\Event\Event;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

class EntrypointsResolved extends Event
{
    /**
     * @param array<string> $requestedEntrypoints
     */
    public function __construct(
        public readonly array $requestedEntrypoints,
        public readonly ResolvedEntrypointCollection $assets,
        public readonly bool $development,
    ) {}
}
