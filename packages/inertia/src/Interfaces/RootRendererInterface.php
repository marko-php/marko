<?php

declare(strict_types=1);

namespace Marko\Inertia\Interfaces;

interface RootRendererInterface
{
    /**
     * Render the initial HTML shell for a page response.
     *
     * @param array<string, mixed> $page
     * @return string Rendered HTML document
     */
    public function render(array $page): string;
}
