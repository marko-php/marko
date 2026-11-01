<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\Latte\Extensions\TemplateFunctionExtension;
use Marko\View\ViewConfig;

readonly class LatteEngineFactory
{
    public function __construct(
        private ViewConfig $viewConfig,
        private LatteViewConfig $latteViewConfig,
        private TemplateFunctionExtension $templateFunctionExtension,
        private CacheDirectoryGuard $cacheDirectoryGuard,
    ) {}

    /**
     * @throws InsecureCacheDirectoryException
     */
    public function create(): Engine
    {
        $engine = new Engine();
        $engine->setTempDirectory($this->cacheDirectoryGuard->prepare($this->viewConfig->cacheDirectory()));
        $engine->setAutoRefresh($this->viewConfig->autoRefresh());
        $engine->setStrictTypes($this->latteViewConfig->strictTypes());
        $engine->addExtension(new SlotExtension());
        $engine->addExtension($this->templateFunctionExtension);

        return $engine;
    }
}
