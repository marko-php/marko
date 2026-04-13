<?php

declare(strict_types=1);

namespace Marko\Vite\Events;

use Marko\Core\Event\Event;
use Marko\Vite\ValueObjects\Manifest;

class ManifestLoaded extends Event
{
    public function __construct(
        public readonly Manifest $manifest,
    ) {}
}
