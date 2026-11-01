<?php

declare(strict_types=1);

namespace Marko\View\Latte\Tests\Fixtures;

use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

#[TemplateFunctions]
class MarkupFunctionProvider implements TemplateFunctionProviderInterface
{
    public function functions(): array
    {
        return [
            new TemplateFunction('escaped', fn (string $label): string => "<b>$label</b>"),
            new TemplateFunction('raw', fn (string $label): string => "<b>$label</b>", isSafeHtml: true),
        ];
    }
}
