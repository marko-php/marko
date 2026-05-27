<?php

declare(strict_types=1);

namespace Marko\View\Twig;

use Marko\Routing\Http\Response;
use Marko\View\TemplateResolverInterface;
use Marko\View\ViewInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TwigView implements ViewInterface
{
    public function __construct(
        private Environment $engine,
        private TemplateResolverInterface $resolver,
    ) {
        // Set our custom loader so includes use the same module resolution
        $this->engine->setLoader(new ModuleLoader($resolver));
    }

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
        return $this->engine->render($template, $data);
    }
}
