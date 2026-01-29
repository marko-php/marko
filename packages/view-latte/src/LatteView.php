<?php

declare(strict_types=1);

namespace Marko\View\Latte;

use Latte\Engine;
use Marko\Routing\Http\Response;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewInterface;

class LatteView implements ViewInterface
{
    public function __construct(
        private Engine $engine,
        private TemplateResolverInterface $resolver,
    ) {
        // Set our custom loader so includes use the same module resolution
        $this->engine->setLoader(new ModuleLoader($resolver));
    }

    public function render(
        string $template,
        array $data = [],
    ): Response {
        $html = $this->engine->renderToString($template, $data);

        return Response::html($html);
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        return $this->engine->renderToString($template, $data);
    }
}
