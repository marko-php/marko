<?php

declare(strict_types=1);

namespace Marko\View;

use Marko\View\Exceptions\TemplatePrecompileException;

/**
 * Compiles one template into the engine's compiled-template cache.
 *
 * The view driver implements only this step. Listing the templates, the loop,
 * collecting failures and reporting live once, in the view:cache command.
 */
interface TemplatePrecompilerInterface
{
    /**
     * Compile a template so that a following render does not compile it again.
     *
     * @param string $template Template name (e.g., 'blog::post/show')
     * @throws TemplatePrecompileException When the template does not compile
     */
    public function precompile(string $template): void;
}
