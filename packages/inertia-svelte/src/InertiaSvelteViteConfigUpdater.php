<?php

declare(strict_types=1);

namespace Marko\Inertia\Svelte;

use Marko\Vite\ScaffoldViteConfigUpdater;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class InertiaSvelteViteConfigUpdater extends ScaffoldViteConfigUpdater
{
    public function __construct(
        ViteConfig $viteConfig,
        \Marko\Core\Path\ProjectPaths $paths,
        \Marko\Vite\ProjectFilePublisher $publisher,
        ScaffoldTemplateRenderer $renderer,
    ) {
        parent::__construct($viteConfig, $paths, $publisher, $renderer);
    }

    public function ensureSvelteConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        $relativePath = $this->viteConfig->rootViteConfigPath;
        $absolutePath = $this->paths->base . '/' . $relativePath;
        $replacement = $this->svelteStub();

        if (is_file($absolutePath)) {
            $contents = (string) file_get_contents($absolutePath);

            $replacement = $this->containsTailwindPlugin($contents)
                ? $this->tailwindSvelteStub($this->entrypointsForExistingConfig($contents))
                : $this->svelteStub();
        }

        return $this->ensureConfig(
            configWhenMissing: $this->svelteStub(),
            configWhenPresent: $replacement,
            pluginNeedles: ['@sveltejs/vite-plugin-svelte', 'plugins: [svelte()]', 'plugins: [svelte(), tailwindcss()]'],
            force: $force,
            dryRun: $dryRun,
        );
    }

    private function containsSveltePlugin(string $contents): bool
    {
        return str_contains($contents, '@sveltejs/vite-plugin-svelte')
            || str_contains($contents, 'plugins: [svelte()]')
            || str_contains($contents, 'plugins: [svelte(), tailwindcss()]');
    }

    private function tailwindStub(): string
    {
        return $this->tailwindSvelteStub([
            $this->viteConfig->rootEntrypointPath,
            'resources/css/app.css',
        ], framework: false);
    }

    private function svelteStub(): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import { svelte } from '@sveltejs/vite-plugin-svelte';"],
            plugins: ['svelte()'],
        );
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindSvelteStub(
        ?array $entrypoints = null,
        bool $framework = true,
    ): string
    {
        return $this->renderer->renderViteConfig(
            imports: $framework
                ? [
                    "import { svelte } from '@sveltejs/vite-plugin-svelte';",
                    "import tailwindcss from '@tailwindcss/vite';",
                ]
                : ["import tailwindcss from '@tailwindcss/vite';"],
            plugins: $framework ? ['svelte()', 'tailwindcss()'] : ['tailwindcss()'],
            entrypoints: $entrypoints ?? [
                $this->viteConfig->rootEntrypointPath,
                'resources/css/app.css',
            ],
        );
    }

    protected function replaceableStubContents(): array
    {
        return [
            ...parent::replaceableStubContents(),
            $this->tailwindStub(),
        ];
    }
}
