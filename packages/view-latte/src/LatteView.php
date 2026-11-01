<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\Routing\Http\Response;
use Marko\View\ViewDataMerger;
use Marko\View\ViewInterface;

/**
 * Renders templates with an engine built by LatteEngineFactory, which already
 * loads templates through the ModuleLoader.
 */
class LatteView implements ViewInterface
{
    public function __construct(
        private Engine $engine,
        private ViewDataMerger $viewDataMerger,
    ) {}

    public function render(
        string $template,
        array $data = [],
    ): Response {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        return $this->engine->renderToString($template, $this->viewDataMerger->merge($data));
    }
}
