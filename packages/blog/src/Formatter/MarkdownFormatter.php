<?php

declare(strict_types=1);

namespace Marko\Blog\Formatter;

use Marko\Blog\Contracts\FormatterInterface;
use Marko\Blog\Events\ContentFormatted;
use Marko\Core\Event\EventDispatcherInterface;

/**
 * Simple Markdown formatter.
 *
 * Converts basic Markdown syntax to HTML. This is a minimal implementation
 * for demonstration purposes. In production, you might replace this with
 * a full Markdown library via a Preference.
 */
class MarkdownFormatter implements FormatterInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function format(string $content): string
    {
        $formatted = $this->convertMarkdown($content);

        $event = new ContentFormatted(
            original: $content,
            formatted: $formatted,
        );

        $this->eventDispatcher->dispatch($event);

        return $event->formatted;
    }

    private function convertMarkdown(string $content): string
    {
        // Simple Markdown conversions for demonstration
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            // Headers
            if (str_starts_with($line, '### ')) {
                $result[] = '<h3>' . htmlspecialchars(substr($line, 4)) . '</h3>';
            } elseif (str_starts_with($line, '## ')) {
                $result[] = '<h2>' . htmlspecialchars(substr($line, 3)) . '</h2>';
            } elseif (str_starts_with($line, '# ')) {
                $result[] = '<h1>' . htmlspecialchars(substr($line, 2)) . '</h1>';
            } else {
                // Bold and italic
                $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
                $line = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $line);

                // Wrap in paragraph if not empty
                if (trim($line) !== '') {
                    $result[] = '<p>' . $line . '</p>';
                }
            }
        }

        return implode("\n", $result);
    }
}
