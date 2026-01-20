<?php

declare(strict_types=1);

namespace Marko\Blog\Contracts;

/**
 * Interface for content formatters.
 *
 * Implementations transform raw content (e.g., Markdown) into formatted output (e.g., HTML).
 */
interface FormatterInterface
{
    /**
     * Format the given content.
     */
    public function format(string $content): string;
}
