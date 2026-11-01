<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\CompileException;
use Latte\Engine;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Exceptions\InvalidTemplateException;
use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\Exceptions\TemplatePrecompileException;
use Marko\View\TemplatePrecompilerInterface;
use Marko\View\TemplateResolverInterface;

/**
 * Compiles one Latte template into the engine's temp directory ahead of time.
 *
 * The engine is built by the same factory as the rendering engine and loads
 * templates through the same ModuleLoader, so the compiled file lands under
 * the key rendering looks up (the resolved absolute path). Auto-refresh is
 * always on here: a compiled file left over from an earlier deploy is
 * recompiled when its template changed, even when view.auto_refresh is off.
 */
class LatteTemplatePrecompiler implements TemplatePrecompilerInterface
{
    private Engine $engine;

    /**
     * @throws InsecureCacheDirectoryException
     */
    public function __construct(
        LatteEngineFactory $engineFactory,
        private TemplateResolverInterface $resolver,
    ) {
        $this->engine = $engineFactory->create();
        $this->engine->setAutoRefresh(true);
        $this->engine->setLoader(new ModuleLoader($resolver));
    }

    /**
     * @throws TemplatePrecompileException|InvalidTemplateException|TemplateNotFoundException
     */
    public function precompile(
        string $template,
    ): void {
        try {
            $this->engine->warmupCache($template);
        } catch (CompileException $exception) {
            throw TemplatePrecompileException::forTemplate(
                template: $template,
                path: $this->resolver->resolve($template),
                line: $exception->position->line ?? 0,
                reason: $exception->getMessage(),
                previous: $exception,
            );
        }
    }
}
