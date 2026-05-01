<?php

declare(strict_types=1);

namespace Marko\Debugbar\Storage;

use JsonException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Throwable;

class DebugbarStorage
{
    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly ProjectPaths $paths,
    ) {}

    /**
     * @param array<string, mixed> $dataset
     */
    public function put(array $dataset): void
    {
        $id = $dataset['id'] ?? null;

        if (! is_string($id) || ! $this->validId($id)) {
            return;
        }

        $directory = $this->directory();

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $directory.'/'.$id.'.json',
            json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );

        $this->prune();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $id): ?array
    {
        if (! $this->validId($id)) {
            return null;
        }

        $file = $this->directory().'/'.$id.'.json';

        if (! is_file($file)) {
            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $this->stringKeyArray($decoded) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $files = glob($this->directory().'/*.json') ?: [];
        $items = [];

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $id = basename($file, '.json');
            $dataset = $this->get($id);

            if ($dataset === null) {
                continue;
            }

            $summary = is_array($dataset['summary'] ?? null)
                ? $this->stringKeyArray($dataset['summary'])
                : [];

            $items[] = [
                'id' => $id,
                'stored_at' => $this->stringValue(
                    $dataset['stored_at'] ?? null,
                    date(DATE_ATOM, (int) filemtime($file)),
                ),
                'profiler_url' => $this->stringValue($dataset['profiler_url'] ?? null, '/_debugbar/'.$id),
                'summary' => $summary,
                'mtime' => (int) filemtime($file),
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => $right['mtime'] <=> $left['mtime'],
        );

        return $items;
    }

    public function clear(): int
    {
        $files = glob($this->directory().'/*.json') ?: [];
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function directory(): string
    {
        $path = $this->configString('debugbar.storage.path', 'storage/debugbar');

        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '/');
        }

        return rtrim($this->paths->base, '/').'/'.trim($path, '/');
    }

    private function prune(): void
    {
        $maxFiles = $this->configInt('debugbar.storage.max_files', 100);

        if ($maxFiles < 1) {
            return;
        }

        $files = glob($this->directory().'/*.json') ?: [];

        if (count($files) <= $maxFiles) {
            return;
        }

        usort(
            $files,
            static fn (string $left, string $right): int => (int) filemtime($right) <=> (int) filemtime($left),
        );

        foreach (array_slice($files, $maxFiles) as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{12,40}$/', $id) === 1;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function configString(
        string $key,
        string $default,
    ): string {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    private function configInt(
        string $key,
        int $default,
    ): int {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function stringKeyArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
