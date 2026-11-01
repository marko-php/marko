<?php

declare(strict_types=1);

namespace Marko\View\Twig\Tests\Fixtures;

use Marko\View\TemplateResolverInterface;

/**
 * Resolves a template name to itself, so tests can build an environment without module templates.
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
