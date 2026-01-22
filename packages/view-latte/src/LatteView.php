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
    ) {}

    public function render(
        string $template,
        array $data = [],
    ): Response {
        $path = $this->resolver->resolve($template);
        $html = $this->engine->renderToString($path, $data);

        return Response::html($html);
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $path = $this->resolver->resolve($template);

        return $this->engine->renderToString($path, $data);
    }
}
