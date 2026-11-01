<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\Routing\Http\Response;
use Marko\View\ViewDataMerger;
use Marko\View\ViewInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Renders templates with an environment built by TwigEngineFactory, which
 * already loads templates through the ModuleLoader.
 */
class TwigView implements ViewInterface
{
    public function __construct(
        private Environment $engine,
        private ViewDataMerger $viewDataMerger,
    ) {}

    /**
     * @param array<string, mixed> $data
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function render(
        string $template,
        array $data = [],
    ): Response {
        $html = $this->renderToString($template, $data);

        return Response::html($html);
    }

    /**
     * @param array<string, mixed> $data
     * @throws LoaderError|RuntimeError|SyntaxError
     */
    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        return $this->engine->render($template, $this->viewDataMerger->merge($data));
    }
}
