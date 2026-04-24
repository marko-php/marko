<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Cache;

use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Contract\ModuleWalkerInterface;
use Marko\CodeIndexer\Contract\TemplateScannerInterface;
use Marko\CodeIndexer\Contract\TranslationScannerInterface;
use Marko\CodeIndexer\Exceptions\IndexCacheException;
use Marko\CodeIndexer\ValueObject\CommandEntry;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\CodeIndexer\ValueObject\ObserverEntry;
use Marko\CodeIndexer\ValueObject\PluginEntry;
use Marko\CodeIndexer\ValueObject\PreferenceEntry;
use Marko\CodeIndexer\ValueObject\RouteEntry;
use Marko\CodeIndexer\ValueObject\TemplateEntry;
use Marko\CodeIndexer\ValueObject\TranslationEntry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IndexCache implements IndexCacheInterface
{
    private const string CACHE_FILE = '.marko/index.cache';

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly string $rootPath,
        private readonly ModuleWalkerInterface $moduleWalker,
        private readonly AttributeParserInterface $attributeParser,
        private readonly ConfigScannerInterface $configScanner,
        private readonly TemplateScannerInterface $templateScanner,
        private readonly TranslationScannerInterface $translationScanner,
    ) {}

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

        $this->data = unserialize((string) file_get_contents($cachePath));

        return true;
    }

    public function isStale(): bool
    {
        $cachePath = $this->rootPath . '/' . self::CACHE_FILE;

        if (!is_file($cachePath)) {
            return true;
        }

        $cacheMtime = filemtime($cachePath);
        $modules = $this->moduleWalker->walk();

        foreach ($modules as $module) {
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

        return false;
    }

    /** @return list<ModuleInfo> */
    public function getModules(): array
    {
        return $this->data['modules'] ?? [];
    }

    /** @return list<ObserverEntry> */
    public function getObservers(): array
    {
        return $this->data['observers'] ?? [];
    }

    /** @return list<PluginEntry> */
    public function getPlugins(): array
    {
        return $this->data['plugins'] ?? [];
    }

    /** @return list<PreferenceEntry> */
    public function getPreferences(): array
    {
        return $this->data['preferences'] ?? [];
    }

    /** @return list<CommandEntry> */
    public function getCommands(): array
    {
        return $this->data['commands'] ?? [];
    }

    /** @return list<RouteEntry> */
    public function getRoutes(): array
    {
        return $this->data['routes'] ?? [];
    }

    /** @return list<ConfigKeyEntry> */
    public function getConfigKeys(): array
    {
        return $this->data['configKeys'] ?? [];
    }

    /** @return list<TemplateEntry> */
    public function getTemplates(): array
    {
        return $this->data['templates'] ?? [];
    }

    /** @return list<TranslationEntry> */
    public function getTranslationKeys(): array
    {
        return $this->data['translationKeys'] ?? [];
    }

    /** @return list<ObserverEntry> */
    public function findObserversForEvent(string $eventClass): array
    {
        return array_values(
            array_filter(
                $this->getObservers(),
                fn (ObserverEntry $o) => $o->event === $eventClass,
            ),
        );
    }

    /** @return list<PluginEntry> */
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

    public function set(string $key, mixed $value): void
    {
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
