<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Cache;

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\Config\ConfigScanner;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Exceptions\IndexCacheException;
use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\Translations\TranslationScanner;
use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\CodeIndexer\ValueObject\TranslationEntry;
use Marko\CodeIndexer\Views\TemplateScanner;
use Marko\Core\Path\ProjectPaths;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IndexCache implements IndexCacheInterface
{
    private const string CACHE_FILE = '.marko/index.cache';

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    private readonly string $rootPath;

    public function __construct(
        ProjectPaths $paths,
        private readonly ModuleWalker $moduleWalker,
        private readonly AttributeParser $attributeParser,
        private readonly ConfigScanner $configScanner,
        private readonly TemplateScanner $templateScanner,
        private readonly TranslationScanner $translationScanner,
    ) {
        $this->rootPath = $paths->base;
    }

    /** @throws IndexCacheException */
    public function build(): void
    {
        $modules = $this->moduleWalker->walk();
        $observers = [];
        $plugins = [];
        $preferences = [];
        $commands = [];
        $routes = [];
        $configKeys = [];
        $templates = [];
        $translationKeys = [];

        foreach ($modules as $module) {
            array_push($observers, ...$this->attributeParser->observers($module));
            array_push($plugins, ...$this->attributeParser->plugins($module));
            array_push($preferences, ...$this->attributeParser->preferences($module));
            array_push($commands, ...$this->attributeParser->commands($module));
            array_push($routes, ...$this->attributeParser->routes($module));
            array_push($configKeys, ...$this->configScanner->scan($module));
            array_push($templates, ...$this->templateScanner->scan($module));
            array_push($translationKeys, ...$this->translationScanner->scan($module));
        }

        $trackedPaths = $this->collectTrackedPaths();

        $this->data = compact(
            'modules',
            'observers',
            'plugins',
            'preferences',
            'commands',
            'routes',
            'configKeys',
            'templates',
            'translationKeys',
            'trackedPaths',
        );

        $this->save();
    }

    /** @throws IndexCacheException */
    private function save(): void
    {
        $cacheDir = $this->rootPath . '/.marko';

        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0755, true)) {
            throw IndexCacheException::cacheDirUnwritable($cacheDir);
        }

        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (@file_put_contents($cachePath, serialize($this->data)) === false) {
            throw IndexCacheException::cacheDirUnwritable($cachePath);
        }
    }

    public function load(): bool
    {
        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (!is_file($cachePath)) {
            return false;
        }

        if ($this->isStale()) {
            return false;
        }

        $result = @unserialize((string) file_get_contents($cachePath), [
            'allowed_classes' => [
                CommandEntry::class,
                ConfigKeyEntry::class,
                ModuleInfo::class,
                ObserverEntry::class,
                PluginEntry::class,
                PreferenceEntry::class,
                RouteEntry::class,
                TemplateEntry::class,
                TranslationEntry::class,
            ],
        ]);

        if (!is_array($result)) {
            return false;
        }

        $this->data = $result;

        return true;
    }

    /**
     * Lazy-load the cache from disk on first read so consumers (MCP server,
     * LSP server, etc.) don't have to remember to call load() at startup.
     * Falls back to a full build() when the cache is missing or stale.
     *
     * On subsequent reads (when $this->data is already populated), re-evaluates
     * staleness so long-lived singleton servers always reflect real changes to
     * app/ and modules/ — without constructing a fresh instance.
     *
     * @throws IndexCacheException
     */
    private function ensureLoaded(): void
    {
        if ($this->data !== null) {
            if ($this->isStale()) {
                $this->build();
            }

            return;
        }

        if ($this->load()) {
            return;
        }

        $this->build();
    }

    public function isStale(): bool
    {
        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (!is_file($cachePath)) {
            return true;
        }

        $cacheMtime = filemtime($cachePath);
        $vendorPrefix = $this->rootPath . '/vendor/';
        $modules = $this->moduleWalker->walk();

        foreach ($modules as $module) {
            // Vendor modules are excluded from the staleness re-check.
            // New vendor packages surface on the next indexer:rebuild / cold-warm.
            if (str_starts_with($module->path, $vendorPrefix)) {
                continue;
            }

            $composerJson = $module->path . '/composer.json';

            if (is_file($composerJson) && filemtime($composerJson) > $cacheMtime) {
                return true;
            }

            foreach (['src', 'config', 'resources/views', 'resources/translations'] as $sub) {
                $dir = $module->path . '/' . $sub;

                if (!is_dir($dir)) {
                    continue;
                }

                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                );

                foreach ($iter as $f) {
                    if ($f->isFile() && $f->getMTime() > $cacheMtime) {
                        return true;
                    }
                }
            }
        }

        // Check for deletions by comparing the current path-set against
        // the previously-persisted set stored in the on-disk payload.
        // We read the payload directly (not $this->data) to avoid the ordering
        // trap: during load(), $this->data is null when isStale() is called.
        $currentPaths = $this->collectTrackedPaths();
        $persistedPaths = $this->loadTrackedPathsFromDisk($cachePath);

        if ($persistedPaths === null) {
            // Legacy payload without trackedPaths key — treat as stale
            return true;
        }

        return $currentPaths !== $persistedPaths;
    }

    /**
     * Collect the sorted list of tracked source-file paths under app/ and modules/.
     * Vendor is intentionally excluded — only app/ and modules/ are re-checked at
     * runtime. Used by build() to persist the set and by isStale() to compare.
     *
     * @return list<string>
     */
    private function collectTrackedPaths(): array
    {
        $paths = [];
        $dirsToScan = [
            $this->rootPath . '/app',
            $this->rootPath . '/modules',
        ];

        foreach ($dirsToScan as $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iter as $f) {
                if ($f->isFile()) {
                    $paths[] = $f->getPathname();
                }
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Read only the `trackedPaths` key from the on-disk cache payload without
     * deserializing the full object graph. Returns null when the key is absent
     * (legacy payload) or when the file cannot be read.
     *
     * @return list<string>|null
     */
    private function loadTrackedPathsFromDisk(string $cachePath): ?array
    {
        $raw = @file_get_contents($cachePath);

        if ($raw === false) {
            return null;
        }

        $data = @unserialize($raw, [
            'allowed_classes' => [
                CommandEntry::class,
                ConfigKeyEntry::class,
                ModuleInfo::class,
                ObserverEntry::class,
                PluginEntry::class,
                PreferenceEntry::class,
                RouteEntry::class,
                TemplateEntry::class,
                TranslationEntry::class,
            ],
        ]);

        if (!is_array($data) || !array_key_exists('trackedPaths', $data)) {
            return null;
        }

        $tracked = $data['trackedPaths'];

        if (!is_array($tracked)) {
            return null;
        }

        return $tracked;
    }

    /**
     * @return list<ModuleInfo>
     * @throws IndexCacheException
     */
    public function getModules(): array
    {
        $this->ensureLoaded();

        return $this->data['modules'] ?? [];
    }

    /**
     * @return list<ObserverEntry>
     * @throws IndexCacheException
     */
    public function getObservers(): array
    {
        $this->ensureLoaded();

        return $this->data['observers'] ?? [];
    }

    /**
     * @return list<PluginEntry>
     * @throws IndexCacheException
     */
    public function getPlugins(): array
    {
        $this->ensureLoaded();

        return $this->data['plugins'] ?? [];
    }

    /**
     * @return list<PreferenceEntry>
     * @throws IndexCacheException
     */
    public function getPreferences(): array
    {
        $this->ensureLoaded();

        return $this->data['preferences'] ?? [];
    }

    /**
     * @return list<CommandEntry>
     * @throws IndexCacheException
     */
    public function getCommands(): array
    {
        $this->ensureLoaded();

        return $this->data['commands'] ?? [];
    }

    /**
     * @return list<RouteEntry>
     * @throws IndexCacheException
     */
    public function getRoutes(): array
    {
        $this->ensureLoaded();

        return $this->data['routes'] ?? [];
    }

    /**
     * @return list<ConfigKeyEntry>
     * @throws IndexCacheException
     */
    public function getConfigKeys(): array
    {
        $this->ensureLoaded();

        return $this->data['configKeys'] ?? [];
    }

    /**
     * @return list<TemplateEntry>
     * @throws IndexCacheException
     */
    public function getTemplates(): array
    {
        $this->ensureLoaded();

        return $this->data['templates'] ?? [];
    }

    /**
     * @return list<TranslationEntry>
     * @throws IndexCacheException
     */
    public function getTranslationKeys(): array
    {
        $this->ensureLoaded();

        return $this->data['translationKeys'] ?? [];
    }

    /**
     * @return list<ObserverEntry>
     * @throws IndexCacheException
     */
    public function findObserversForEvent(string $eventClass): array
    {
        return array_values(
            array_filter(
                $this->getObservers(),
                fn (ObserverEntry $o) => $o->event === $eventClass,
            ),
        );
    }

    /**
     * @return list<PluginEntry>
     * @throws IndexCacheException
     */
    public function findPluginsForTarget(string $targetClass): array
    {
        return array_values(
            array_filter(
                $this->getPlugins(),
                fn (PluginEntry $p) => $p->target === $targetClass,
            ),
        );
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function invalidate(): void
    {
        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (is_file($cachePath)) {
            @unlink($cachePath);
        }

        $this->data = null;
    }
}
