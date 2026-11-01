<?php

declare(strict_types=1);

namespace Marko\View\Exceptions;

use Throwable;

/**
 * A template failed to compile ahead of time.
 *
 * Engine precompilers wrap their own compile error in this exception, so the
 * view:cache command reports every engine's failures the same way.
 */
class TemplatePrecompileException extends ViewException
{
    public function __construct(
        string $message,
        public readonly string $template,
        public readonly string $path,
        public readonly int $templateLine,
        string $context = '',
        string $suggestion = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            context: $context,
            suggestion: $suggestion,
            previous: $previous,
        );
    }

    /**
     * @param string $template Template name (e.g., 'blog::post/show')
     * @param string $path Resolved template file
     * @param int $line Line of the error in the template file
     * @param string $reason The engine's error message
     */
    public static function forTemplate(
        string $template,
        string $path,
        int $line,
        string $reason,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: "Template '$template' failed to compile: $reason",
            template: $template,
            path: $path,
            templateLine: $line,
            context: "In $path:$line",
            suggestion: 'Fix the template syntax at the reported line, then run view:cache again.',
            previous: $previous,
        );
    }
}
