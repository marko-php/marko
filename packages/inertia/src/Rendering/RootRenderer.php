<?php

declare(strict_types=1);

namespace Marko\Inertia\Rendering;

use Marko\Inertia\InertiaConfig;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Interfaces\SsrGatewayInterface;
use Marko\Vite\Contracts\ViteManagerInterface;

class RootRenderer implements RootRendererInterface
{
    public function __construct(
        private readonly InertiaConfig $config,
        private readonly ViteManagerInterface $vite,
        private readonly SsrGatewayInterface $ssr,
    ) {}

    public function render(array $page): string
    {
        $title = $this->resolveTitle($page);
        $ssr = $this->ssr->render($page);
        $body = $ssr?->body ?? $this->renderClientRoot($page);
        $head = $ssr?->head ?? [];

        return implode("\n", [
            '<!DOCTYPE html>',
            '<html lang="en">',
            '<head>',
            '<meta charset="utf-8">',
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            "<title>$title</title>",
            ...$head,
            $this->vite->tags(),
            '</head>',
            '<body>',
            $body,
            '</body>',
            '</html>',
        ]);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function renderClientRoot(array $page): string
    {
        $rootId = htmlspecialchars($this->config->rootElementId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pageJson = str_replace(
            '</script>',
            '<\/script>',
            json_encode($page, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );

        return implode('', [
            "<script data-page=\"$rootId\" type=\"application/json\">$pageJson</script>",
            "<div id=\"$rootId\"></div>",
        ]);
    }

    /**
     * @param array<string, mixed> $page
     */
    private function resolveTitle(array $page): string
    {
        $title = $this->config->rootTitle();
        $pageTitle = $page['props']['title'] ?? null;

        if (is_string($pageTitle) && $pageTitle !== '') {
            $title = $pageTitle;
        }

        return htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
