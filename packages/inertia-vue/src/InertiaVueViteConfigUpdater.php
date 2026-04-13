<?php

declare(strict_types=1);

namespace Marko\Inertia\Vue;

use Marko\Vite\ScaffoldViteConfigUpdater;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class InertiaVueViteConfigUpdater extends ScaffoldViteConfigUpdater
{
    public function __construct(
        ViteConfig $viteConfig,
        \Marko\Core\Path\ProjectPaths $paths,
        \Marko\Vite\ProjectFilePublisher $publisher,
        ScaffoldTemplateRenderer $renderer,
    ) {
        parent::__construct($viteConfig, $paths, $publisher, $renderer);
    }

    public function ensureVueConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        $relativePath = $this->viteConfig->rootViteConfigPath;
        $absolutePath = $this->paths->base . '/' . $relativePath;
        $replacement = $this->vueStub();

        if (is_file($absolutePath)) {
            $contents = (string) file_get_contents($absolutePath);

            $replacement = $this->containsTailwindPlugin($contents)
                ? $this->tailwindVueStub($this->entrypointsForExistingConfig($contents))
                : $this->vueStub();
        }

        return $this->ensureConfig(
            configWhenMissing: $this->vueStub(),
            configWhenPresent: $replacement,
            pluginNeedles: ['@vitejs/plugin-vue', 'plugins: [vue()]', 'plugins: [vue(), tailwindcss()]'],
            force: $force,
            dryRun: $dryRun,
        );
    }

    private function containsVuePlugin(string $contents): bool
    {
        return str_contains($contents, '@vitejs/plugin-vue')
            || str_contains($contents, 'plugins: [vue()]')
            || str_contains($contents, 'plugins: [vue(), tailwindcss()]');
    }

    private function tailwindStub(): string
    {
        return $this->tailwindVueStub([
            $this->viteConfig->rootEntrypointPath,
            'resources/css/app.css',
        ], framework: false);
    }

    private function vueStub(): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import vue from '@vitejs/plugin-vue';"],
            plugins: ['vue()'],
        );
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindVueStub(
        ?array $entrypoints = null,
        bool $framework = true,
    ): string
    {
        return $this->renderer->renderViteConfig(
            imports: $framework
                ? [
                    "import vue from '@vitejs/plugin-vue';",
                    "import tailwindcss from '@tailwindcss/vite';",
                ]
                : ["import tailwindcss from '@tailwindcss/vite';"],
            plugins: $framework ? ['vue()', 'tailwindcss()'] : ['tailwindcss()'],
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
