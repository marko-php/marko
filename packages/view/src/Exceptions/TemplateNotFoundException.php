<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

class TemplateNotFoundException extends ViewException
{
    /**
     * @param array<string> $searchedPaths
     */
    public static function forTemplate(
        string $templateName,
        array $searchedPaths,
    ): self {
        return new self(
            message: "Template '$templateName' not found.",
            context: "Searched paths:\n" . implode("\n", $searchedPaths),
            suggestion: 'Verify the template name and ensure it exists in one of the configured view directories.',
        );
    }
}
