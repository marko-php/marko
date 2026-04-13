<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Path\ProjectPaths;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\ViteConfig;

abstract class ScaffoldViteConfigUpdater
{
    public function __construct(
        protected readonly ViteConfig $viteConfig,
        protected readonly ProjectPaths $paths,
        protected readonly ProjectFilePublisher $publisher,
        protected readonly ScaffoldTemplateRenderer $renderer,
    ) {}

    protected function ensureConfig(
        string $configWhenMissing,
        string $configWhenPresent,
        array $pluginNeedles,
        bool $force = false,
        bool $dryRun = false,
    ): FilePublishResult {
        $relativePath = $this->viteConfig->rootViteConfigPath;
        $absolutePath = $this->paths->base . '/' . $relativePath;

        if (! is_file($absolutePath)) {
            return $this->publisher->publish($relativePath, $configWhenMissing, false, $dryRun);
        }

        $contents = (string) file_get_contents($absolutePath);

        if ($this->containsAnyNeedle($contents, $pluginNeedles) || $this->normalized($contents) === $this->normalized(
            $configWhenPresent
        )) {
            return new FilePublishResult($relativePath, 'already_present');
        }

        if ($this->isReplaceableViteStub($contents) || $force) {
            return $this->publisher->publish($relativePath, $configWhenPresent, true, $dryRun);
        }

        return new FilePublishResult($relativePath, 'skipped');
    }

    protected function containsTailwindPlugin(string $contents): bool
    {
        return $this->containsAnyNeedle($contents, [
            '@tailwindcss/vite',
            'tailwindcss()',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function entrypointsForExistingConfig(string $contents): array
    {
        $entrypoints = [$this->viteConfig->rootEntrypointPath];

        if (! preg_match('/entrypoints:\s*\[([^\]]*)\]/s', $contents, $matches)) {
            return $entrypoints;
        }

        preg_match_all("/'([^']+)'/", $matches[1], $entrypointMatches);

        foreach ($entrypointMatches[1] ?? [] as $candidate) {
            if ($candidate === $this->viteConfig->rootEntrypointPath) {
                continue;
            }

            $entrypoints[] = $candidate;
        }

        return array_values(array_unique($entrypoints));
    }

    protected function normalized(string $contents): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $contents));
    }

    protected function isReplaceableViteStub(string $contents): bool
    {
        $normalized = $this->normalized($contents);

        foreach ($this->replaceableStubContents() as $stub) {
            if ($normalized === $this->normalized($stub)) {
                return true;
            }
        }

        if ($this->looksLikeScaffoldConfig($contents)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function replaceableStubContents(): array
    {
        return [
            $this->renderer->renderViteConfig(),
            "export { default } from './modules/vite/resources/config/vite.config';\n",
            "export { default } from './vendor/marko/vite/resources/config/vite.config.ts';\n",
            "export { default } from './modules/tailwindcss/resources/config/vite.config';\n",
            "export { default } from './vendor/marko/tailwindcss/resources/config/vite.config.ts';\n",
        ];
    }

    /**
     * @param list<string> $needles
     */
    private function containsAnyNeedle(
        string $contents,
        array $needles,
    ): bool {
        foreach ($needles as $needle) {
            if (str_contains($contents, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeScaffoldConfig(string $contents): bool
    {
        return str_contains($contents, 'createBaseConfig({')
            && str_contains($contents, 'entrypoints: [');
    }
}
