<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Contract\AttributeParserInterface;
use Marko\CodeIndexer\Contract\ConfigScannerInterface;
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

// ── helpers ─────────────────────────────────────────────────────────────────

function makeTempDir(): string
{
    $dir = sys_get_temp_dir() . '/codeindexer-test-' . uniqid();
    mkdir($dir, 0755, true);
    return $dir;
}

function makeModule(string $path): ModuleInfo
{
    return new ModuleInfo(
        name: 'test/module',
        path: $path,
        namespace: 'Test\\Module',
    );
}

function makeIndexObserver(): ObserverEntry
{
    return new ObserverEntry(
        class: 'Test\\Module\\Observers\\FooObserver',
        event: 'Test\\Module\\Events\\FooEvent',
        method: 'handle',
        sortOrder: 10,
    );
}

function makeIndexPlugin(): PluginEntry
{
    return new PluginEntry(
        class: 'Test\\Module\\Plugins\\FooPlugin',
        target: 'Test\\Module\\Services\\FooService',
        method: 'execute',
        type: 'before',
        sortOrder: 5,
    );
}

function makeIndexPreference(): PreferenceEntry
{
    return new PreferenceEntry(
        interface: 'Test\\Module\\Contracts\\FooInterface',
        implementation: 'Test\\Module\\Services\\FooImpl',
        module: 'test/module',
    );
}

function makeIndexCommand(): CommandEntry
{
    return new CommandEntry(
        name: 'test:run',
        class: 'Test\\Module\\Commands\\RunCommand',
        description: 'Run tests',
    );
}

function makeIndexRoute(): RouteEntry
{
    return new RouteEntry(
        method: 'GET',
        path: '/test',
        class: 'Test\\Module\\Controllers\\TestController',
        action: 'index',
    );
}

function makeIndexConfigKey(): ConfigKeyEntry
{
    return new ConfigKeyEntry(
        key: 'test.value',
        type: 'string',
        defaultValue: 'hello',
        module: 'test/module',
    );
}

function makeIndexTemplate(): TemplateEntry
{
    return new TemplateEntry(
        moduleName: 'test/module',
        templateName: 'index',
        absolutePath: '/path/to/index.latte',
        extension: 'latte',
    );
}

function makeIndexTranslation(): TranslationEntry
{
    return new TranslationEntry(
        key: 'welcome',
        group: 'messages',
        locale: 'en',
        namespace: null,
        file: '/path/to/en/messages.php',
        line: 1,
        module: 'test/module',
    );
}

function makeIndexCache(
    string $rootPath,
    ModuleWalkerInterface $walker,
    AttributeParserInterface $attributeParser,
    ConfigScannerInterface $configScanner,
    TemplateScannerInterface $templateScanner,
    TranslationScannerInterface $translationScanner,
): IndexCache {
    return new IndexCache(
        rootPath: $rootPath,
        moduleWalker: $walker,
        attributeParser: $attributeParser,
        configScanner: $configScanner,
        templateScanner: $templateScanner,
        translationScanner: $translationScanner,
    );
}

// ── shared stubs ─────────────────────────────────────────────────────────────

$tmpDirs = [];

afterEach(function () use (&$tmpDirs): void {
    foreach ($tmpDirs as $dir) {
        if (is_dir($dir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($it as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($dir);
        }
    }
    $tmpDirs = [];
});

// ── tests ─────────────────────────────────────────────────────────────────────

it('it builds a fresh index by running all scanners and walkers', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $module = makeModule($rootPath . '/module');
    mkdir($module->path, 0755, true);

    $observer = makeIndexObserver();
    $plugin = makeIndexPlugin();
    $preference = makeIndexPreference();
    $command = makeIndexCommand();
    $route = makeIndexRoute();
    $configKey = makeIndexConfigKey();
    $template = makeIndexTemplate();
    $translation = makeIndexTranslation();

    $walker = new class ($module) implements ModuleWalkerInterface
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };

    $attributeParser = new class ($observer, $plugin, $preference, $command, $route) implements AttributeParserInterface
    {
        public function __construct(
            private readonly ObserverEntry $observer,
            private readonly PluginEntry $plugin,
            private readonly PreferenceEntry $preference,
            private readonly CommandEntry $command,
            private readonly RouteEntry $route,
        ) {}

        public function observers(ModuleInfo $m): array
        {
            return [$this->observer];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [$this->plugin];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [$this->preference];
        }

        public function commands(ModuleInfo $m): array
        {
            return [$this->command];
        }

        public function routes(ModuleInfo $m): array
        {
            return [$this->route];
        }
    };

    $configScanner = new class ($configKey) implements ConfigScannerInterface
    {
        public function __construct(private readonly ConfigKeyEntry $key) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->key];
        }
    };

    $templateScanner = new class ($template) implements TemplateScannerInterface
    {
        public function __construct(private readonly TemplateEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };

    $translationScanner = new class ($translation) implements TranslationScannerInterface
    {
        public function __construct(private readonly TranslationEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    expect($cache->getModules())->toHaveCount(1)
        ->and($cache->getObservers())->toHaveCount(1)
        ->and($cache->getPlugins())->toHaveCount(1)
        ->and($cache->getPreferences())->toHaveCount(1)
        ->and($cache->getCommands())->toHaveCount(1)
        ->and($cache->getRoutes())->toHaveCount(1)
        ->and($cache->getConfigKeys())->toHaveCount(1)
        ->and($cache->getTemplates())->toHaveCount(1)
        ->and($cache->getTranslationKeys())->toHaveCount(1);
});

it('it writes serialized cache to .marko/index.cache', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $walker = new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class () implements AttributeParserInterface
    {
        public function observers(ModuleInfo $m): array
        {
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    $cacheFile = $rootPath . '/.marko/index.cache';
    expect(file_exists($cacheFile))->toBeTrue();

    $raw = file_get_contents($cacheFile);
    $data = unserialize($raw);
    expect($data)->toBeArray()
        ->and($data)->toHaveKey('modules')
        ->and($data)->toHaveKey('observers')
        ->and($data)->toHaveKey('plugins')
        ->and($data)->toHaveKey('preferences')
        ->and($data)->toHaveKey('commands')
        ->and($data)->toHaveKey('routes')
        ->and($data)->toHaveKey('configKeys')
        ->and($data)->toHaveKey('templates')
        ->and($data)->toHaveKey('translationKeys');
});

it('it loads cache from disk without re-scanning when cache is fresh', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $scanCount = 0;

    $walker = new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class ($scanCount) implements AttributeParserInterface
    {
        public function __construct(private int &$count) {}

        public function observers(ModuleInfo $m): array
        {
            $this->count++;
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build and save cache
    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    // Load on a fresh instance — should not re-invoke scanners
    $cache2 = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $loaded = $cache2->load();

    expect($loaded)->toBeTrue()
        ->and($scanCount)->toBe(0); // attributeParser->observers was never called during load
});

it('it invalidates cache when any source file mtime exceeds cache mtime', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Create a module with a src/ file
    $modulePath = $rootPath . '/module';
    mkdir($modulePath . '/src', 0755, true);
    file_put_contents($modulePath . '/src/Foo.php', '<?php class Foo {}');

    $module = makeModule($modulePath);

    $walker = new class ($module) implements ModuleWalkerInterface
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class () implements AttributeParserInterface
    {
        public function observers(ModuleInfo $m): array
        {
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build the cache
    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    // Touch the source file to simulate a newer mtime
    $cacheFile = $rootPath . '/.marko/index.cache';
    touch($modulePath . '/src/Foo.php', filemtime($cacheFile) + 10);

    // Load on fresh instance — should refuse because cache is stale
    $cache2 = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $loaded = $cache2->load();

    expect($loaded)->toBeFalse();
});

it('it exposes getModules, getObservers, getPlugins, getPreferences, getCommands, getRoutes, getConfigKeys, getTemplates, getTranslationKeys methods', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $module = makeModule($rootPath . '/module');
    mkdir($module->path, 0755, true);

    $observer = makeIndexObserver();
    $plugin = makeIndexPlugin();
    $preference = makeIndexPreference();
    $command = makeIndexCommand();
    $route = makeIndexRoute();
    $configKey = makeIndexConfigKey();
    $template = makeIndexTemplate();
    $translation = makeIndexTranslation();

    $walker = new class ($module) implements ModuleWalkerInterface
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class ($observer, $plugin, $preference, $command, $route) implements AttributeParserInterface
    {
        public function __construct(
            private readonly ObserverEntry $observer,
            private readonly PluginEntry $plugin,
            private readonly PreferenceEntry $preference,
            private readonly CommandEntry $command,
            private readonly RouteEntry $route,
        ) {}

        public function observers(ModuleInfo $m): array
        {
            return [$this->observer];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [$this->plugin];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [$this->preference];
        }

        public function commands(ModuleInfo $m): array
        {
            return [$this->command];
        }

        public function routes(ModuleInfo $m): array
        {
            return [$this->route];
        }
    };
    $configScanner = new class ($configKey) implements ConfigScannerInterface
    {
        public function __construct(private readonly ConfigKeyEntry $key) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->key];
        }
    };
    $templateScanner = new class ($template) implements TemplateScannerInterface
    {
        public function __construct(private readonly TemplateEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };
    $translationScanner = new class ($translation) implements TranslationScannerInterface
    {
        public function __construct(private readonly TranslationEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    expect($cache->getModules()[0])->toBe($module)
        ->and($cache->getObservers()[0])->toBe($observer)
        ->and($cache->getPlugins()[0])->toBe($plugin)
        ->and($cache->getPreferences()[0])->toBe($preference)
        ->and($cache->getCommands()[0])->toBe($command)
        ->and($cache->getRoutes()[0])->toBe($route)
        ->and($cache->getConfigKeys()[0])->toBe($configKey)
        ->and($cache->getTemplates()[0])->toBe($template)
        ->and($cache->getTranslationKeys()[0])->toBe($translation);
});

it('it provides inverse indexes: find observers listening to a given event class, find plugins targeting a given class', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $module = makeModule($rootPath . '/module');
    mkdir($module->path, 0755, true);

    $matchingObserver = makeIndexObserver(); // event: 'Test\Module\Events\FooEvent'
    $otherObserver = new ObserverEntry(
        class: 'Test\\Module\\Observers\\BarObserver',
        event: 'Test\\Module\\Events\\BarEvent',
        method: 'handle',
        sortOrder: 0,
    );

    $matchingPlugin = makeIndexPlugin(); // target: 'Test\Module\Services\FooService'
    $otherPlugin = new PluginEntry(
        class: 'Test\\Module\\Plugins\\BarPlugin',
        target: 'Test\\Module\\Services\\BarService',
        method: 'run',
        type: 'after',
        sortOrder: 0,
    );

    $walker = new class ($module) implements ModuleWalkerInterface
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class ($matchingObserver, $otherObserver, $matchingPlugin, $otherPlugin) implements AttributeParserInterface
    {
        public function __construct(
            private readonly ObserverEntry $obs1,
            private readonly ObserverEntry $obs2,
            private readonly PluginEntry $plug1,
            private readonly PluginEntry $plug2,
        ) {}

        public function observers(ModuleInfo $m): array
        {
            return [$this->obs1, $this->obs2];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [$this->plug1, $this->plug2];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    $fooObservers = $cache->findObserversForEvent('Test\\Module\\Events\\FooEvent');
    expect($fooObservers)->toHaveCount(1)
        ->and($fooObservers[0]->class)->toBe('Test\\Module\\Observers\\FooObserver');

    $emptyObservers = $cache->findObserversForEvent('Test\\Module\\Events\\UnknownEvent');
    expect($emptyObservers)->toBeEmpty();

    $fooPlugins = $cache->findPluginsForTarget('Test\\Module\\Services\\FooService');
    expect($fooPlugins)->toHaveCount(1)
        ->and($fooPlugins[0]->class)->toBe('Test\\Module\\Plugins\\FooPlugin');

    $emptyPlugins = $cache->findPluginsForTarget('Test\\Module\\Services\\UnknownService');
    expect($emptyPlugins)->toBeEmpty();
});

it('it throws IndexCacheException with helpful suggestion when cache dir is unwritable', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Create .marko dir and make it unreadable/unwritable
    $markoDir = $rootPath . '/.marko';
    mkdir($markoDir, 0000, true);

    $walker = new class () implements ModuleWalkerInterface
    {
        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class () implements AttributeParserInterface
    {
        public function observers(ModuleInfo $m): array
        {
            return [];
        }

        public function plugins(ModuleInfo $m): array
        {
            return [];
        }

        public function preferences(ModuleInfo $m): array
        {
            return [];
        }

        public function commands(ModuleInfo $m): array
        {
            return [];
        }

        public function routes(ModuleInfo $m): array
        {
            return [];
        }
    };
    $configScanner = new class () implements ConfigScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () implements TemplateScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () implements TranslationScannerInterface
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    $thrown = null;
    try {
        $cache->build();
    } catch (IndexCacheException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(IndexCacheException::class)
        ->and($thrown->getMessage())->toContain('Cannot write to index cache')
        ->and($thrown->getSuggestion())->not->toBeEmpty()
        ->and($thrown->getSuggestion())->toContain('.marko');

    // Restore permissions so afterEach cleanup can delete the dir
    chmod($markoDir, 0755);
});
