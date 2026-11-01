<?php

declare(strict_types=1);

namespace Marko\View\Latte\Tests\Fixtures;

use Marko\View\TemplateResolverInterface;

/**
 * Resolves a template name to itself, so tests can render a template by its absolute path.
 */
class PathTemplateResolver implements TemplateResolverInterface
{
    public function resolve(string $template): string
    {
        return $template;
    }

    public function getSearchedPaths(string $template): array
    {
        return [$template];
    }

    public function all(): array
    {
        return [];
    }
}
