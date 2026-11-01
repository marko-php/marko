<?php

declare(strict_types=1);

namespace Marko\View\Latte\Extensions;

use Latte\Extension;
use Latte\Runtime\Html;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionRegistry;
use Stringable;

/**
 * Registers every discovered marko/view template function with Latte.
 *
 * A function declared isSafeHtml has its result wrapped in Latte\Runtime\Html
 * so Latte prints it unescaped; every other result is escaped as usual.
 */
class TemplateFunctionExtension extends Extension
{
    public function __construct(
        private readonly TemplateFunctionRegistry $templateFunctionRegistry,
    ) {}

    /**
     * @return array<string, callable>
     * @throws ViewProviderException|DiscoveryCacheException
     */
    public function getFunctions(): array
    {
        $functions = [];

        foreach ($this->templateFunctionRegistry->all() as $function) {
            $functions[$function->name] = $this->adapt($function);
        }

        return $functions;
    }

    private function adapt(
        TemplateFunction $function,
    ): callable {
        if (!$function->isSafeHtml) {
            return $function->callable;
        }

        $callable = $function->callable;

        return function (mixed ...$arguments) use ($callable): Html {
            /** @var string|Stringable $html */
            $html = $callable(...$arguments);

            return new Html((string) $html);
        };
    }
}
