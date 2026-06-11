<?php

declare(strict_types=1);

namespace Marko\Translation\File\Loader;

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\Exceptions\TranslationException;

class FileTranslationLoader implements TranslationLoaderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /** @var array<string, string> */
    private array $namespaces = [];

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @throws TranslationException
     */
    public function load(
        string $locale,
        string $group,
        ?string $namespace = null,
    ): array {
        $cacheKey = $namespace !== null ? "$namespace::$locale.$group" : "$locale.$group";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $path = $this->resolveFilePath($locale, $group, $namespace);

        if (!file_exists($path)) {
            $this->cache[$cacheKey] = [];

            return [];
        }

        $translations = require $path;

        $this->cache[$cacheKey] = is_array($translations) ? $translations : [];

        return $this->cache[$cacheKey];
    }

    /**
     * Register a namespace with its language directory path.
     */
    public function addNamespace(
        string $namespace,
        string $path,
    ): void {
        $this->namespaces[$namespace] = $path;
    }

    /**
     * Resolve the file path for a given locale, group, and optional namespace.
     *
     * @throws TranslationException
     */
    private function resolveFilePath(
        string $locale,
        string $group,
        ?string $namespace,
    ): string {
        $this->assertValidPathSegment($locale, 'locale');
        $this->assertValidPathSegment($group, 'group');

        if ($namespace === null) {
            return $this->basePath . '/lang/' . $locale . '/' . $group . '.php';
        }

        $this->assertValidPathSegment($namespace, 'namespace');

        if (!isset($this->namespaces[$namespace])) {
            throw TranslationException::namespaceNotRegistered($namespace, $locale, $group);
        }

        return $this->namespaces[$namespace] . '/' . $locale . '/' . $group . '.php';
    }

    /**
     * Assert that a path segment contains only safe characters.
     *
     * @throws TranslationException
     */
    private function assertValidPathSegment(
        string $value,
        string $segment,
    ): void {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw TranslationException::invalidPathSegment($segment, $value);
        }
    }
}
