<?php

declare(strict_types=1);

namespace Marko\Vite\Events;

use Marko\Core\Event\Event;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

class AssetTagsRendered extends Event
{
    public function __construct(
        public readonly ResolvedEntrypointCollection $assets,
        public readonly string $html,
        public readonly string $kind,
    ) {}
}
