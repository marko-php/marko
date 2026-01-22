<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\View\ViewConfig;

class LatteEngineFactory
{
    public function __construct(
        private ViewConfig $viewConfig,
    ) {}

    public function create(): Engine
    {
        $engine = new Engine();
        $engine->setTempDirectory($this->viewConfig->cacheDirectory());
        $engine->setAutoRefresh($this->viewConfig->autoRefresh());
        $engine->setStrictTypes($this->viewConfig->strictTypes());

        return $engine;
    }
}
