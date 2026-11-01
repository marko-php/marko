<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\Latte\Extensions\TemplateFunctionExtension;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewConfig;

/**
 * Builds fully configured Latte engines: cache directory, strict types,
 * extensions, and a ModuleLoader so templates and their includes resolve
 * through the module template resolver.
 */
readonly class LatteEngineFactory
{
    public function __construct(
        private ViewConfig $viewConfig,
        private LatteViewConfig $latteViewConfig,
        private TemplateFunctionExtension $templateFunctionExtension,
        private CacheDirectoryGuard $cacheDirectoryGuard,
        private TemplateResolverInterface $resolver,
    ) {}

    /**
     * Build an engine that refreshes compiled templates per view.auto_refresh.
     *
     * @throws InsecureCacheDirectoryException
     */
    public function create(): Engine
    {
        return $this->build($this->viewConfig->autoRefresh());
    }

    /**
     * Build an engine that always recompiles a template changed since its
     * last compile, whatever view.auto_refresh says.
     *
     * @throws InsecureCacheDirectoryException
     */
    public function createWithAutoRefresh(): Engine
    {
        return $this->build(true);
    }

    /**
     * @throws InsecureCacheDirectoryException
     */
    private function build(
        bool $autoRefresh,
    ): Engine {
        $engine = new Engine();
        $engine->setTempDirectory($this->cacheDirectoryGuard->prepare($this->viewConfig->cacheDirectory()));
        $engine->setAutoRefresh($autoRefresh);
        $engine->setStrictTypes($this->latteViewConfig->strictTypes());
        $engine->setLoader(new ModuleLoader($this->resolver));
        $engine->addExtension(new SlotExtension());
        $engine->addExtension($this->templateFunctionExtension);

        return $engine;
    }
}
