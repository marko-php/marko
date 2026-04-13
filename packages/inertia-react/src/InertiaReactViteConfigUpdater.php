<?php

declare(strict_types=1);

namespace Marko\Inertia\React;

use Marko\Vite\ScaffoldViteConfigUpdater;
use Marko\Vite\ScaffoldTemplateRenderer;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

class InertiaReactViteConfigUpdater extends ScaffoldViteConfigUpdater
{
    public function __construct(
        ViteConfig $viteConfig,
        \Marko\Core\Path\ProjectPaths $paths,
        \Marko\Vite\ProjectFilePublisher $publisher,
        ScaffoldTemplateRenderer $renderer,
    ) {
        parent::__construct($viteConfig, $paths, $publisher, $renderer);
    }

    public function ensureReactConfig(
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        $relativePath = $this->viteConfig->rootViteConfigPath;
        $absolutePath = $this->paths->base . '/' . $relativePath;
        $replacement = $this->reactStub();

        if (is_file($absolutePath)) {
            $contents = (string) file_get_contents($absolutePath);

            $replacement = $this->containsTailwindPlugin($contents)
                ? $this->tailwindReactStub($this->entrypointsForExistingConfig($contents))
                : $this->reactStub();
        }

        return $this->ensureConfig(
            configWhenMissing: $this->reactStub(),
            configWhenPresent: $replacement,
            pluginNeedles: ['@vitejs/plugin-react', 'plugins: [react()]', 'plugins: [react(), tailwindcss()]'],
            force: $force,
            dryRun: $dryRun,
        );
    }

    private function containsReactPlugin(string $contents): bool
    {
        return str_contains($contents, '@vitejs/plugin-react')
            || str_contains($contents, 'plugins: [react()]')
            || str_contains($contents, 'plugins: [react(), tailwindcss()]');
    }

    private function tailwindStub(): string
    {
        return $this->tailwindReactStub([
            $this->viteConfig->rootEntrypointPath,
            'resources/css/app.css',
        ], framework: false);
    }

    private function reactStub(): string
    {
        return $this->renderer->renderViteConfig(
            imports: ["import react from '@vitejs/plugin-react';"],
            plugins: ['react()'],
        );
    }

    /**
     * @param list<string>|null $entrypoints
     */
    private function tailwindReactStub(
        ?array $entrypoints = null,
        bool $framework = true,
    ): string
    {
        return $this->renderer->renderViteConfig(
            imports: $framework
                ? [
                    "import react from '@vitejs/plugin-react';",
                    "import tailwindcss from '@tailwindcss/vite';",
                ]
                : ["import tailwindcss from '@tailwindcss/vite';"],
            plugins: $framework ? ['react()', 'tailwindcss()'] : ['tailwindcss()'],
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
