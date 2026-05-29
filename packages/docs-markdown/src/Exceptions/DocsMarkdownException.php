<?php

declare(strict_types=1);

namespace Marko\DocsMarkdown\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DocsMarkdownException extends MarkoException
{
    public static function pageNotFound(string $id, string $docsPath): self
    {
        return new self(
            message: "Markdown page '$id' not found",
            context: "Looking in $docsPath",
            suggestion: 'Check the page ID matches a .md file under the docs/ directory',
        );
    }
}
