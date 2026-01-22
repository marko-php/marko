<?php

declare(strict_types=1);

namespace Marko\Mail\Contracts;

/**
 * Interface for view/template rendering.
 *
 * This interface represents an optional dependency - when marko/view
 * is installed, it will provide an implementation. When not installed,
 * ViewMailer gracefully degrades to pass-through behavior.
 */
interface ViewInterface
{
    /**
     * Render a template with the given data.
     *
     * @param array<string, mixed> $data
     */
    public function render(
        string $template,
        array $data = [],
    ): string;
}
