<?php

declare(strict_types=1);

namespace Marko\View\Tests\Fixtures;

use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

#[TemplateFunctions]
class ConflictingFunctionProvider implements TemplateFunctionProviderInterface
{
    public function functions(): array
    {
        return [
            new TemplateFunction('greet', fn (): string => 'Hi'),
        ];
    }
}
