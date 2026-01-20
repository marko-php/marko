<?php

declare(strict_types=1);

namespace Marko\Blog\Events;

use Marko\Core\Event\Event;

/**
 * Dispatched after content has been formatted.
 *
 * Observers can use this to log, modify, or react to formatted content.
 * The formatted property can be modified by observers to alter the final output.
 */
class ContentFormatted extends Event
{
    public function __construct(
        public readonly string $original,
        public string $formatted,
    ) {}
}
