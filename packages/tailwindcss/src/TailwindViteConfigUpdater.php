<?php

declare(strict_types=1);

namespace Marko\TailwindCss;

use Marko\TailwindCss\Contracts\TailwindEntrypointProviderInterface;
use Marko\Vite\ScaffoldViteConfigUpdater;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class TailwindViteConfigUpdater extends ScaffoldViteConfigUpdater
{
    public function __construct(
        ViteConfig $viteConfig,
        \Marko\Core\Path\ProjectPaths $paths,
        \Marko\Vite\ProjectFilePublisher $publisher,
        private readonly TailwindEntrypointProviderInterface $entrypointProvider,
        ScaffoldTemplateRenderer $renderer,
    ) {
        parent::__construct($viteConfig, $paths, $publisher, $renderer);
    }

    public function ensureTailwindConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult
    {
        $relativePath = $this->viteConfig->rootViteConfigPath;
        $absolutePath = $this->paths->base . '/' . $relativePath;
        $replacement = $this->tailwindStub();

        if (is_file($absolutePath)) {
            $contents = (string) file_get_contents($absolutePath);
            $entrypoints = $this->entrypointsWithTailwind($contents);

            $replacement = match (true) {
                $this->containsVuePlugin($contents) => $this->tailwindVueStub($entrypoints),
                $this->containsReactPlugin($contents) => $this->tailwindReactStub($entrypoints),
                $this->containsSveltePlugin($contents) => $this->tailwindSvelteStub($entrypoints),
                default => $this->tailwindStub($entrypoints),
            };
        }

        return $this->ensureConfig(
            configWhenMissing: $this->tailwindStub(),
            configWhenPresent: $replacement,
            pluginNeedles: ['@tailwindcss/vite', 'tailwindcss()'],
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

    private function containsReactPlugin(string $contents): bool
    {
        return str_contains($contents, '@vitejs/plugin-react')
            || str_contains($contents, 'plugins: [react()]')
            || str_contains($contents, 'plugins: [react(), tailwindcss()]');
    }

    private function containsSveltePlugin(string $contents): bool
    {
        return str_contains($contents, '@sveltejs/vite-plugin-svelte')
            || str_contains($contents, 'plugins: [svelte()]')
            || str_contains($contents, 'plugins: [svelte(), tailwindcss()]');
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindStub(?array $entrypoints = null): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import tailwindcss from '@tailwindcss/vite';"],
            plugins: ['tailwindcss()'],
            entrypoints: $entrypoints ?? [
                $this->viteConfig->rootEntrypointPath,
                $this->tailwindEntrypoint(),
            ],
        );
    }

    private function vueStub(): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import vue from '@vitejs/plugin-vue';"],
            plugins: ['vue()'],
        );
    }

    private function reactStub(): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import react from '@vitejs/plugin-react';"],
            plugins: ['react()'],
        );
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
    private function tailwindVueStub(?array $entrypoints = null): string
    {
        return $this->renderer->renderViteConfig(
            imports: [
                "import vue from '@vitejs/plugin-vue';",
                "import tailwindcss from '@tailwindcss/vite';",
            ],
            plugins: ['vue()', 'tailwindcss()'],
            entrypoints: $entrypoints ?? [
                $this->viteConfig->rootEntrypointPath,
                $this->tailwindEntrypoint(),
            ],
        );
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindReactStub(?array $entrypoints = null): string
    {
        return $this->renderer->renderViteConfig(
            imports: [
                "import react from '@vitejs/plugin-react';",
                "import tailwindcss from '@tailwindcss/vite';",
            ],
            plugins: ['react()', 'tailwindcss()'],
            entrypoints: $entrypoints ?? [
                $this->viteConfig->rootEntrypointPath,
                $this->tailwindEntrypoint(),
            ],
        );
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindSvelteStub(?array $entrypoints = null): string
    {
        return $this->renderer->renderViteConfig(
            imports: [
                "import { svelte } from '@sveltejs/vite-plugin-svelte';",
                "import tailwindcss from '@tailwindcss/vite';",
            ],
            plugins: ['svelte()', 'tailwindcss()'],
            entrypoints: $entrypoints ?? [
                $this->viteConfig->rootEntrypointPath,
                $this->tailwindEntrypoint(),
            ],
        );
    }

    private function tailwindEntrypoint(): string
    {
        return $this->entrypointProvider->entrypoints()[0] ?? 'resources/css/app.css';
    }

    /**
     * @return list<string>
     */
    private function entrypointsWithTailwind(string $contents): array
    {
        $entrypoints = $this->entrypointsForExistingConfig($contents);
        $entrypoints[] = $this->tailwindEntrypoint();

        return array_values(array_unique($entrypoints));
    }

    protected function replaceableStubContents(): array
    {
        return [
            ...parent::replaceableStubContents(),
            $this->renderer->renderViteConfig(
                imports: ["import vue from '@vitejs/plugin-vue';"],
                plugins: ['vue()'],
            ),
            $this->renderer->renderViteConfig(
                imports: ["import react from '@vitejs/plugin-react';"],
                plugins: ['react()'],
            ),
            $this->renderer->renderViteConfig(
                imports: ["import { svelte } from '@sveltejs/vite-plugin-svelte';"],
                plugins: ['svelte()'],
            ),
            $this->tailwindStub(),
        ];
    }
}
