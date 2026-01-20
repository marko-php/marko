<?php

declare(strict_types=1);

namespace App\Blog\Plugins;

use Marko\Blog\Formatter\MarkdownFormatter;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Plugin;

/**
 * Modifies the MarkdownFormatter's output.
 *
 * This demonstrates how an application can intercept and modify behavior
 * from a vendor package without modifying the package itself.
 */
#[Plugin(target: MarkdownFormatter::class)]
class FormatterPlugin
{
    #[After(sortOrder: 10)]
    public function afterFormat(
        string $result,
        string $content,
    ): string
    {
        // Wrap the formatted content in a container div
        return '<div class="blog-content">' . "\n" . $result . "\n" . '</div>';
    }
}
