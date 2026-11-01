<?php

declare(strict_types=1);

namespace Marko\View;

/**
 * Supplies functions to every template, whatever the installed engine.
 *
 * Mark the implementing class with #[TemplateFunctions] so it is discovered.
 */
interface TemplateFunctionProviderInterface
{
    /**
     * @return list<TemplateFunction>
     */
    public function functions(): array;
}
