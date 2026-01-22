<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\Routing\Http\Response;

interface ViewInterface
{
    /**
     * Render a template and return an HTTP response.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @param array<string, mixed> $data Variables to pass to template
     * @return Response HTML response with rendered content
     */
    public function render(
        string $template,
        array $data = [],
    ): Response;

    /**
     * Render a template and return the HTML string.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @param array<string, mixed> $data Variables to pass to template
     * @return string Rendered HTML content
     */
    public function renderToString(
        string $template,
        array $data = [],
    ): string;
}
