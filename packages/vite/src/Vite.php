<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Path\ProjectPaths;
use Marko\Vite\Exceptions\ViteConfigurationException;
use Marko\Vite\Exceptions\ViteManifestException;

readonly class Vite
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private ProjectPaths $paths,
    ) {}

    /**
     * Generate Vite script/link tags for the HTML head.
     */
    public function headTags(?string $entry = null): string
    {
        $entry = $this->normalizeEntry($entry ?? $this->configuredEntry());

        if ($this->useDevServer()) {
            return $this->devServerTags($entry);
        }

        return $this->manifestTags($entry);
    }

    /**
     * Check if Vite dev server should be used.
     */
    public function useDevServer(): bool
    {
        return $this->configBool('vite.useDevServer');
    }

    /**
     * Tags for Vite dev server (hot module replacement).
     */
    private function devServerTags(string $entry): string
    {
        $url = $this->devServerUrl();

        $tags = '';

        foreach ($this->devServerStylesheets() as $stylesheet) {
            $stylesheet = ltrim($stylesheet, '/');
            $tags .= "<link rel=\"stylesheet\" href=\"$url/$stylesheet\">\n";
        }

        if ($this->isReactEntry($entry)) {
            $tags .= $this->reactRefreshPreamble($url)."\n";
        }

        $tags .= <<<HTML
<script type="module" src="$url/@vite/client"></script>
<script type="module" src="$url/$entry"></script>
HTML;

        return $tags;
    }

    /**
     * React Fast Refresh expects this preamble when Vite is not serving the
     * application HTML itself.
     */
    private function reactRefreshPreamble(string $url): string
    {
        return <<<HTML
<script type="module">
import { injectIntoGlobalHook } from "$url/@react-refresh";
injectIntoGlobalHook(window);
window.\$RefreshReg\$ = () => {};
window.\$RefreshSig\$ = () => (type) => type;
</script>
HTML;
    }

    private function isReactEntry(string $entry): bool
    {
        return str_ends_with($entry, '.jsx') || str_ends_with($entry, '.tsx');
    }

    /**
     * Stylesheets served directly by Vite in dev mode.
     *
     * @return list<string>
     */
    private function devServerStylesheets(): array
    {
        $stylesheets = $this->configArray('vite.devServerStylesheets');

        return array_values(array_filter(
            $stylesheets,
            static fn (mixed $stylesheet): bool => is_string($stylesheet) && $stylesheet !== '',
        ));
    }

    /**
     * Tags from production build manifest.
     */
    private function manifestTags(string $entry): string
    {
        $manifestPath = $this->manifestPath();

        if (! is_file($manifestPath)) {
            throw ViteManifestException::notFound($manifestPath);
        }

        $manifestContents = file_get_contents($manifestPath);
        if ($manifestContents === false) {
            throw ViteManifestException::unreadable($manifestPath);
        }

        $manifest = $this->decodeManifest($manifestContents);
        if ($manifest === null) {
            throw ViteManifestException::invalid($manifestPath);
        }

        $buildDir = $this->configString('vite.buildDirectory');
        $basePath = '/'.trim($buildDir, '/').'/';

        $entryData = $manifest[$entry] ?? null;
        if (! is_array($entryData)) {
            throw ViteManifestException::entryNotFound($entry, $manifestPath);
        }

        $entryFile = $entryData['file'] ?? null;
        if (! is_string($entryFile) || $entryFile === '') {
            throw ViteManifestException::entryFileInvalid($entry, $manifestPath);
        }

        $importedChunks = $this->importedChunks($manifest, $entryData);
        $stylesheets = [];

        foreach (array_merge([$entryData], $importedChunks) as $chunk) {
            if (! isset($chunk['css']) || ! is_array($chunk['css'])) {
                continue;
            }

            foreach ($chunk['css'] as $css) {
                if (! is_string($css) || $css === '') {
                    continue;
                }

                $stylesheets[$css] = "<link rel=\"stylesheet\" href=\"$basePath$css\">";
            }
        }

        $tags = array_values($stylesheets);
        $tags[] = $this->entryTag($basePath, $entryFile);

        foreach ($importedChunks as $chunk) {
            $chunkFile = $chunk['file'] ?? null;

            if (! is_string($chunkFile) || $chunkFile === '' || str_ends_with($chunkFile, '.css')) {
                continue;
            }

            $tags[] = "<link rel=\"modulepreload\" href=\"$basePath$chunkFile\">";
        }

        return implode("\n", $tags);
    }

    /**
     * Resolve the absolute path to the Vite manifest file.
     */
    private function manifestPath(): string
    {
        $buildDir = $this->configString('vite.buildDirectory');
        $manifestFilename = $this->configString('vite.manifestFilename');

        return $this->paths->base.'/public/'.trim($buildDir, '/').'/'.$manifestFilename;
    }

    private function configuredEntry(): string
    {
        return $this->configString('vite.entry');
    }

    private function normalizeEntry(string $entry): string
    {
        $entry = trim($entry);

        if ($entry === '') {
            throw ViteConfigurationException::empty(
                'vite.entry',
                'Set vite.entry in config/vite.php or pass a non-empty entry to Vite::headTags().',
            );
        }

        return ltrim($entry, '/');
    }

    private function devServerUrl(): string
    {
        $url = trim($this->configString('vite.devServerUrl'));

        if ($url === '') {
            throw ViteConfigurationException::empty(
                'vite.devServerUrl',
                'Set vite.devServerUrl in config/vite.php when vite.useDevServer is true.',
            );
        }

        return rtrim($url, '/');
    }

    private function configString(string $key): string
    {
        try {
            return $this->config->getString($key);
        } catch (ConfigException $exception) {
            throw ViteConfigurationException::missingOrInvalid($key, $exception);
        }
    }

    private function configBool(string $key): bool
    {
        try {
            return $this->config->getBool($key);
        } catch (ConfigException $exception) {
            throw ViteConfigurationException::missingOrInvalid($key, $exception);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function configArray(string $key): array
    {
        try {
            return $this->config->getArray($key);
        } catch (ConfigException $exception) {
            throw ViteConfigurationException::missingOrInvalid($key, $exception);
        }
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    private function decodeManifest(string $manifestContents): ?array
    {
        $manifest = json_decode($manifestContents, true);

        if (! is_array($manifest)) {
            return null;
        }

        $normalizedManifest = [];

        foreach ($manifest as $key => $chunk) {
            if (! is_string($key)) {
                return null;
            }

            $normalizedChunk = $this->normalizeChunk($chunk);

            if ($normalizedChunk === null) {
                return null;
            }

            $normalizedManifest[$key] = $normalizedChunk;
        }

        return $normalizedManifest;
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed> $entryData
     * @return list<array<string, mixed>>
     */
    private function importedChunks(
        array $manifest,
        array $entryData,
    ): array {
        $seen = [];

        return array_values($this->collectImportedChunks($manifest, $entryData, $seen));
    }

    /**
     * @param array<string, array<string, mixed>> $manifest
     * @param array<string, mixed> $chunk
     * @param array<string, true> $seen
     * @return array<string, array<string, mixed>>
     */
    private function collectImportedChunks(
        array $manifest,
        array $chunk,
        array &$seen,
    ): array {
        $imports = $chunk['imports'] ?? null;
        $chunks = [];

        if (! is_array($imports)) {
            return $chunks;
        }

        foreach ($imports as $import) {
            if (! is_string($import) || isset($seen[$import])) {
                continue;
            }

            $importedChunk = $manifest[$import] ?? null;

            if (! is_array($importedChunk)) {
                continue;
            }

            $seen[$import] = true;
            $chunks[$import] = $importedChunk;

            foreach ($this->collectImportedChunks($manifest, $importedChunk, $seen) as $nestedImport => $nestedChunk) {
                $chunks[$nestedImport] = $nestedChunk;
            }
        }

        return $chunks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeChunk(mixed $chunk): ?array
    {
        if (! is_array($chunk)) {
            return null;
        }

        $normalizedChunk = [];

        foreach ($chunk as $key => $value) {
            if (! is_string($key)) {
                return null;
            }

            $normalizedChunk[$key] = $value;
        }

        return $normalizedChunk;
    }

    private function entryTag(
        string $basePath,
        string $entryFile,
    ): string {
        if (str_ends_with($entryFile, '.css')) {
            return "<link rel=\"stylesheet\" href=\"$basePath$entryFile\">";
        }

        return "<script type=\"module\" src=\"$basePath$entryFile\"></script>";
    }
}
