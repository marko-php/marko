<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Latte\Feature;
use Marko\View\Latte\Extensions\SlotExtension;
use Marko\View\ViewConfig;

readonly class LatteEngineFactory
{
    public function __construct(
        private ViewConfig $viewConfig,
    ) {}

    public function create(): Engine
    {
        $engine = new Engine();
        $engine->setTempDirectory($this->viewConfig->cacheDirectory());
        $engine->setAutoRefresh($this->viewConfig->autoRefresh());
        $engine->setFeature(Feature::StrictTypes, $this->viewConfig->strictTypes());
        $engine->addExtension(new SlotExtension());

        return $engine;
    }
}
