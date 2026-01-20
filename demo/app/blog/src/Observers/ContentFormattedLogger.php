<?php

declare(strict_types=1);

namespace App\Blog\Observers;

use Marko\Blog\Events\ContentFormatted;
use Marko\Core\Attributes\Observer;

/**
 * Logs when content is formatted.
 *
 * This demonstrates how an application can observe events from a vendor package
 * without modifying the package itself.
 */
#[Observer(event: ContentFormatted::class, priority: 100)]
class ContentFormattedLogger
{
    /** @var array<string> */
    public static array $logs = [];

    public function handle(ContentFormatted $event): void
    {
        $originalLength = strlen($event->original);
        $formattedLength = strlen($event->formatted);

        self::$logs[] = "Content formatted: {$originalLength} chars → {$formattedLength} chars";
    }
}
