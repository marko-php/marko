<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Vite\Contracts\AssetUrlGeneratorInterface;
use Marko\Vite\Contracts\DefaultEntrypointProviderInterface;
use Marko\Vite\Contracts\DevServerResolverInterface;
use Marko\Vite\Contracts\EntrypointResolverInterface;
use Marko\Vite\Contracts\ManifestRepositoryInterface;
use Marko\Vite\Events\EntrypointsResolved;
use Marko\Vite\ValueObjects\ManifestEntry;
use Marko\Vite\ValueObjects\ResolvedAsset;
use Marko\Vite\ValueObjects\ResolvedEntrypointCollection;

class EntrypointResolver implements EntrypointResolverInterface
{
    public function __construct(
        private readonly DefaultEntrypointProviderInterface $defaultEntrypointProvider,
        private readonly DevServerResolverInterface $devServerResolver,
        private readonly ManifestRepositoryInterface $manifestRepository,
        private readonly AssetUrlGeneratorInterface $assetUrlGenerator,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function resolve(string|array|null $entrypoints = null): ResolvedEntrypointCollection
    {
        $requested = $this->normalizeEntrypoints($entrypoints);
        $resolved = $this->devServerResolver->isDevelopment()
            ? $this->resolveDevelopment($requested)
            : $this->resolveProduction($requested);

        $this->events->dispatch(new EntrypointsResolved(
            requestedEntrypoints: $requested,
            assets: $resolved,
            development: $resolved->development,
        ));

        return $resolved;
    }

    /**
     * @param array<string> $entrypoints
     */
    protected function resolveDevelopment(array $entrypoints): ResolvedEntrypointCollection
    {
        $devServer = $this->devServerResolver->resolve();
        $scripts = [];
        $styles = [];
        $scriptUrls = [];

        $clientUrl = $devServer->assetUrl('@vite/client');
        $scripts[] = new ResolvedAsset('@vite/client', $clientUrl, 'script', true);
        $scriptUrls[$clientUrl] = true;

        foreach ($entrypoints as $entrypoint) {
            $asset = new ResolvedAsset(
                entrypoint: $entrypoint,
                url: $devServer->assetUrl($entrypoint),
                kind: str_ends_with($entrypoint, '.css') ? 'style' : 'script',
                development: true,
            );

            if ($asset->kind === 'style') {
                $styles[] = $asset;
                continue;
            }

            if (!isset($scriptUrls[$asset->url])) {
                $scripts[] = $asset;
                $scriptUrls[$asset->url] = true;
            }
        }

        return new ResolvedEntrypointCollection(
            entrypoints: $entrypoints,
            preloads: [],
            styles: $styles,
            scripts: $scripts,
            development: true,
        );
    }

    /**
     * @param array<string> $entrypoints
     */
    protected function resolveProduction(array $entrypoints): ResolvedEntrypointCollection
    {
        $scripts = [];
        $styles = [];
        $preloads = [];
        $styleUrls = [];
        $preloadUrls = [];
        $scriptUrls = [];

        foreach ($entrypoints as $entrypoint) {
            $entry = $this->manifestRepository->entry($entrypoint);

            if ($entry->isCss()) {
                $style = $this->makeAsset($entrypoint, $entry->file, 'style', false);
                if (!isset($styleUrls[$style->url])) {
                    $styles[] = $style;
                    $styleUrls[$style->url] = true;
                }
                continue;
            }

            $script = $this->makeAsset($entrypoint, $entry->file, 'script', false);
            if (!isset($scriptUrls[$script->url])) {
                $scripts[] = $script;
                $scriptUrls[$script->url] = true;
            }

            foreach ($this->resolveStylesForEntry($entry, []) as $stylePath) {
                $style = $this->makeAsset($entrypoint, $stylePath, 'style', false);
                if (!isset($styleUrls[$style->url])) {
                    $styles[] = $style;
                    $styleUrls[$style->url] = true;
                }
            }

            foreach ($this->resolveImportsForEntry($entry, []) as $importPath) {
                $preload = $this->makeAsset($entrypoint, $importPath, 'preload', false);
                if (!isset($preloadUrls[$preload->url])) {
                    $preloads[] = $preload;
                    $preloadUrls[$preload->url] = true;
                }
            }
        }

        return new ResolvedEntrypointCollection(
            entrypoints: $entrypoints,
            preloads: $preloads,
            styles: $styles,
            scripts: $scripts,
            development: false,
        );
    }

    /**
     * @param array<string, bool> $visited
     * @return array<string>
     */
    protected function resolveStylesForEntry(
        ManifestEntry $entry,
        array $visited,
    ): array
    {
        if (isset($visited[$entry->name])) {
            return [];
        }

        $visited[$entry->name] = true;
        $styles = $entry->css;

        foreach ($entry->imports as $import) {
            $styles = [...$styles, ...$this->resolveStylesForEntry(
                $this->manifestRepository->entry($import),
                $visited
            )];
        }

        return array_values(array_unique($styles));
    }

    /**
     * @param array<string, bool> $visited
     * @return array<string>
     */
    protected function resolveImportsForEntry(
        ManifestEntry $entry,
        array $visited,
    ): array
    {
        if (isset($visited[$entry->name])) {
            return [];
        }

        $visited[$entry->name] = true;
        $imports = [];

        foreach ($entry->imports as $import) {
            $importEntry = $this->manifestRepository->entry($import);
            if (!$importEntry->isCss()) {
                $imports[] = $importEntry->file;
            }

            $imports = [...$imports, ...$this->resolveImportsForEntry($importEntry, $visited)];
        }

        return array_values(array_unique($imports));
    }

    protected function makeAsset(
        string $entrypoint,
        string $path,
        string $kind,
        bool $development,
    ): ResolvedAsset {
        return new ResolvedAsset(
            entrypoint: $entrypoint,
            url: $development ? $path : $this->assetUrlGenerator->generate($path),
            kind: $kind,
            development: $development,
        );
    }

    /**
     * @return array<string>
     */
    protected function normalizeEntrypoints(string|array|null $entrypoints): array
    {
        if ($entrypoints === null) {
            return $this->defaultEntrypointProvider->entrypoints();
        }

        if (is_string($entrypoints)) {
            return [$entrypoints];
        }

        return array_values(array_map('strval', $entrypoints));
    }
}
