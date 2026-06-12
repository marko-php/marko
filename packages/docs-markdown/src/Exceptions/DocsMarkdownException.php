<?php

declare(strict_types=1);

namespace Marko\DocsMarkdown\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DocsMarkdownException extends MarkoException
{
    public static function pageNotFound(
        string $id,
        string $docsPath,
    ): self {
        return new self(
            message: "Markdown page '$id' not found",
            context: "Looking in $docsPath",
            suggestion: 'Check the page ID matches a .md file under the docs/ directory',
        );
    }

    public static function pathTraversal(
        string $id,
        string $docsPath,
    ): self {
        return new self(
            message: "Page id '$id' attempts to read outside the docs root",
            context: "Docs root is $docsPath",
            suggestion: 'Use a page id that is relative to the docs root without path-traversal segments (..)',
        );
    }
}
