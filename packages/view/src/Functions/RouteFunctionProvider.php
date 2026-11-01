<?php

declare(strict_types=1);

namespace Marko\View\Functions;

use Marko\Routing\UrlGeneratorInterface;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

/**
 * Adds route() to every template, backed by UrlGeneratorInterface:
 *
 *     Latte: <a href={route('shows.show', [id: $show->id])}>...</a>
 *     Twig:  <a href="{{ route('shows.show', {id: show.id}) }}">...</a>
 *
 * The URL is escaped like any other output.
 */
#[TemplateFunctions]
readonly class RouteFunctionProvider implements TemplateFunctionProviderInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function functions(): array
    {
        return [
            new TemplateFunction('route', $this->urlGenerator->route(...)),
        ];
    }
}
