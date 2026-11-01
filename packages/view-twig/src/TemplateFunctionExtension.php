<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Registers every discovered marko/view template function with Twig.
 *
 * A function declared isSafeHtml is registered with is_safe => ['html'] so
 * Twig prints it unescaped; every other result is escaped as usual.
 */
class TemplateFunctionExtension extends AbstractExtension
{
    public function __construct(
        private readonly TemplateFunctionRegistry $templateFunctionRegistry,
    ) {}

    /**
     * @return list<TwigFunction>
     * @throws ViewProviderException|DiscoveryCacheException
     */
    public function getFunctions(): array
    {
        return array_map(
            fn (TemplateFunction $function): TwigFunction => new TwigFunction(
                $function->name,
                $function->callable,
                $function->isSafeHtml ? ['is_safe' => ['html']] : [],
            ),
            $this->templateFunctionRegistry->all(),
        );
    }
}
