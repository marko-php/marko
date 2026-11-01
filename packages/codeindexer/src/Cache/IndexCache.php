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
use Marko\Core\Support\ErrorCapture;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IndexCache implements IndexCacheInterface
{
    private const string CACHE_FILE = '.marko/index.cache';

    /** Module name recorded on config keys that come from the project-root `config/` directory. */
    public const string ROOT_CONFIG_MODULE = 'project';

    /**
     * Files Composer rewrites on every require/update/remove, in order of preference.
     * The first one that exists is fingerprinted to detect vendor changes.
     */
    private const array COMPOSER_STATE_FILES = [
        'vendor/composer/installed.json',
        'composer.lock',
    ];

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

        // The project-root config/ belongs to no module but defines app-level keys
        // and overrides, so it is indexed as well.
        array_push(
            $configKeys,
            ...$this->configScanner->scanDirectory($this->rootPath . '/config', self::ROOT_CONFIG_MODULE),
        );

        $trackedPaths = $this->collectTrackedPaths();
        $composerState = $this->composerStateFingerprint();

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
            'composerState',
        );

        $this->save();
    }

    /** @throws IndexCacheException */
    private function save(): void
    {
        $cacheDir = $this->rootPath . '/.marko';

        if (!is_dir($cacheDir)
            && !ErrorCapture::run($reason, fn (): bool => mkdir($cacheDir, 0755, true))
            && !is_dir($cacheDir)
        ) {
            throw IndexCacheException::cacheDirUnwritable($cacheDir, $reason);
        }

        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        $payload = serialize($this->data);

        if (ErrorCapture::run($reason, fn (): int|false => file_put_contents($cachePath, $payload)) === false) {
            throw IndexCacheException::cacheDirUnwritable($cachePath, $reason);
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

        // A corrupt or unreadable cache is not an error: load() returns false and the
        // caller rebuilds. Capture the warnings so they are not emitted; the reason
        // is intentionally discarded.
        $raw = ErrorCapture::run($reason, fn (): string|false => file_get_contents($cachePath));

        if ($raw === false) {
            return false;
        }

        $result = $this->unserializeCache($raw);

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
     * app/, modules/, the root config/ and Composer-installed packages — without
     * constructing a fresh instance.
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

        // Long-lived servers stat the same files repeatedly; drop PHP's stat cache
        // so changes made by other processes (editors, composer) are seen.
        clearstatcache();

        if (!is_file($cachePath)) {
            return true;
        }

        $cacheMtime = filemtime($cachePath);
        $vendorPrefix = $this->rootPath . '/vendor/';
        $dirsToCheck = [$this->rootPath . '/config'];

        foreach ($this->moduleWalker->walk() as $module) {
            // Vendor modules are not walked file by file: Composer rewrites
            // vendor/composer/installed.json on every require/update/remove, so the
            // composer-state fingerprint below covers them with one stat call.
            if (str_starts_with($module->path, $vendorPrefix)) {
                continue;
            }

            $composerJson = $module->path . '/composer.json';

            if (is_file($composerJson) && filemtime($composerJson) > $cacheMtime) {
                return true;
            }

            foreach (['src', 'config', 'resources/views', 'resources/translations'] as $sub) {
                $dirsToCheck[] = $module->path . '/' . $sub;
            }
        }

        if (array_any($dirsToCheck, fn (string $dir): bool => $this->hasFileNewerThan($dir, $cacheMtime))) {
            return true;
        }

        // Compare the current Composer state and tracked path-set (which catches
        // added and deleted files) against the values persisted in the on-disk
        // payload. We read the payload directly (not $this->data) to avoid the
        // ordering trap: during load(), $this->data is null when isStale() is called.
        $persisted = $this->loadPayloadFromDisk($cachePath);

        if ($persisted === null
            || !is_array($persisted['trackedPaths'] ?? null)
            || !array_key_exists('composerState', $persisted)
        ) {
            // Unreadable or legacy payload without these keys — treat as stale
            return true;
        }

        if ($persisted['composerState'] !== $this->composerStateFingerprint()) {
            return true;
        }

        return $this->collectTrackedPaths() !== $persisted['trackedPaths'];
    }

    private function hasFileNewerThan(
        string $dir,
        int $mtime,
    ): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iter as $f) {
            if ($f->isFile() && $f->getMTime() > $mtime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fingerprint of the Composer install state: the path, mtime and size of
     * vendor/composer/installed.json, falling back to composer.lock. It changes on
     * every composer require/update/remove. Null when neither file exists.
     */
    private function composerStateFingerprint(): ?string
    {
        foreach (self::COMPOSER_STATE_FILES as $relative) {
            $file = $this->rootPath . '/' . $relative;

            if (is_file($file)) {
                return $relative . ':' . filemtime($file) . ':' . filesize($file);
            }
        }

        return null;
    }

    /**
     * Collect the sorted list of tracked source-file paths under app/, modules/
     * and the project-root config/. Vendor is not listed file by file; the
     * composer-state fingerprint covers it. Used by build() to persist the set
     * and by isStale() to detect added or deleted files.
     *
     * @return list<string>
     */
    private function collectTrackedPaths(): array
    {
        $paths = [];
        $dirsToScan = [
            $this->rootPath . '/app',
            $this->rootPath . '/modules',
            $this->rootPath . '/config',
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
     * Read the on-disk cache payload for the staleness comparison. Returns null
     * when the file cannot be read or does not deserialize to an array.
     *
     * @return array<string, mixed>|null
     */
    private function loadPayloadFromDisk(string $cachePath): ?array
    {
        $raw = ErrorCapture::run($reason, fn (): string|false => file_get_contents($cachePath));

        if ($raw === false) {
            return null;
        }

        $data = $this->unserializeCache($raw);

        return is_array($data) ? $data : null;
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

    /**
     * Unserialize a cache payload restricted to the indexer value objects.
     *
     * A corrupt payload makes unserialize() return false with a notice; the notice
     * is captured (not emitted) because a corrupt cache simply triggers a rebuild.
     */
    private function unserializeCache(string $raw): mixed
    {
        return ErrorCapture::run($reason, fn (): mixed => unserialize($raw, [
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
        ]));
    }

    /** @throws IndexCacheException */
    public function invalidate(): void
    {
        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (is_file($cachePath)) {
            if (!ErrorCapture::run($reason, fn (): bool => unlink($cachePath))) {
                throw IndexCacheException::cacheNotRemovable($cachePath, $reason);
            }
        }

        $this->data = null;
    }
}
