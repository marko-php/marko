<?php

declare(strict_types=1);

namespace Marko\View\Tests\Fixtures;

use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

#[TemplateFunctions]
class GreetingFunctionProvider implements TemplateFunctionProviderInterface
{
    public function functions(): array
    {
        return [
            new TemplateFunction('greet', fn (string $name): string => "Hello <$name>"),
            new TemplateFunction('badge', fn (string $label): string => "<b>$label</b>", isSafeHtml: true),
        ];
    }
}
