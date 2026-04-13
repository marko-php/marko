<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Vite\Contracts\ManifestRepositoryInterface;
use Marko\Vite\Events\ManifestLoaded;
use Marko\Vite\Exceptions\ManifestNotFoundException;
use Marko\Vite\ValueObjects\Manifest;
use Marko\Vite\ValueObjects\ManifestEntry;
use Marko\Vite\ValueObjects\ViteConfig;

class ManifestRepository implements ManifestRepositoryInterface
{
    private ?Manifest $manifest = null;

    public function __construct(
        private readonly ViteConfig $config,
        private readonly EventDispatcherInterface $events,
    ) {}

    public function manifest(): Manifest
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifestPath = $this->resolveManifestPath();

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            throw ManifestNotFoundException::forPath($manifestPath);
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw ManifestNotFoundException::invalidJson(
                $manifestPath,
                json_last_error_msg(),
            );
        }

        $entries = [];

        foreach ($decoded as $name => $entry) {
            if (!is_array($entry) || !isset($entry['file'])) {
                continue;
            }

            $entries[(string) $name] = new ManifestEntry(
                name: (string) $name,
                file: (string) $entry['file'],
                source: isset($entry['src']) ? (string) $entry['src'] : null,
                isEntry: (bool) ($entry['isEntry'] ?? false),
                css: array_values(array_map('strval', $entry['css'] ?? [])),
                imports: array_values(array_map('strval', $entry['imports'] ?? [])),
            );
        }

        $this->manifest = new Manifest($manifestPath, $entries);
        $this->events->dispatch(new ManifestLoaded($this->manifest));

        return $this->manifest;
    }

    public function entry(string $entrypoint): ManifestEntry
    {
        return $this->manifest()->entry($entrypoint);
    }

    private function resolveManifestPath(): string
    {
        if (is_file($this->config->manifestPath)) {
            return $this->config->manifestPath;
        }

        $legacyPath = preg_replace('#/manifest\.json$#', '/.vite/manifest.json', $this->config->manifestPath);

        if (is_string($legacyPath) && $legacyPath !== '' && is_file($legacyPath)) {
            return $legacyPath;
        }

        throw ManifestNotFoundException::forPath($this->config->manifestPath);
    }
}
