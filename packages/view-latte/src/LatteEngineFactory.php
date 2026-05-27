<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\ViewConfig;

readonly class LatteEngineFactory
{
    public function __construct(
        private ViewConfig $viewConfig,
        private LatteViewConfig $latteViewConfig,
    ) {}

    public function create(): Engine
    {
        $engine = new Engine();
        $engine->setTempDirectory($this->viewConfig->cacheDirectory());
        $engine->setAutoRefresh($this->viewConfig->autoRefresh());
        $engine->setStrictTypes($this->latteViewConfig->strictTypes());
        $engine->addExtension(new SlotExtension());

        return $engine;
    }
}
