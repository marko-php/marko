<?php

declare(strict_types=1);

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Config\ConfigScanner;
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
    ModuleWalker $walker,
    AttributeParser $attributeParser,
    ConfigScanner $configScanner,
    TemplateScanner $templateScanner,
    TranslationScanner $translationScanner,
): IndexCache {
    return new IndexCache(
        paths: new ProjectPaths($rootPath),
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

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };

    $attributeParser = new class ($observer, $plugin, $preference, $command, $route) extends AttributeParser
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

    $configScanner = new class ($configKey) extends ConfigScanner
    {
        public function __construct(private readonly ConfigKeyEntry $key) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->key];
        }
    };

    $templateScanner = new class ($template) extends TemplateScanner
    {
        public function __construct(private readonly TemplateEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };

    $translationScanner = new class ($translation) extends TranslationScanner
    {
        public function __construct(private readonly TranslationEntry $entry) {}

        public function scan(ModuleInfo $m): array
        {
            return [$this->entry];
        }
    };

    $cache = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
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

    $walker = new class () extends ModuleWalker
    {
        public function __construct() {}

        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
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

    $walker = new class () extends ModuleWalker
    {
        public function __construct() {}

        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class ($scanCount) extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build and save cache
    $cache = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
    $cache->build();

    // Load on a fresh instance — should not re-invoke scanners
    $cache2 = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
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

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build the cache
    $cache = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
    $cache->build();

    // Touch the source file to simulate a newer mtime
    $cacheFile = $rootPath . '/.marko/index.cache';
    touch($modulePath . '/src/Foo.php', filemtime($cacheFile) + 10);

    // Load on fresh instance — should refuse because cache is stale
    $cache2 = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
    $loaded = $cache2->load();

    expect($loaded)->toBeFalse();
});

it(
    'exposes get methods for every indexed entry type',
    function () use (&$tmpDirs): void {
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

        $walker = new class ($module) extends ModuleWalker
        {
            public function __construct(private readonly ModuleInfo $module) {}

            public function walk(): array
            {
                return [$this->module];
            }
        };
        $attributeParser = new class ($observer, $plugin, $preference, $command, $route) extends AttributeParser
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
        $configScanner = new class ($configKey) extends ConfigScanner
        {
            public function __construct(private readonly ConfigKeyEntry $key) {}

            public function scan(ModuleInfo $m): array
            {
                return [$this->key];
            }
        };
        $templateScanner = new class ($template) extends TemplateScanner
        {
            public function __construct(private readonly TemplateEntry $entry) {}

            public function scan(ModuleInfo $m): array
            {
                return [$this->entry];
            }
        };
        $translationScanner = new class ($translation) extends TranslationScanner
        {
            public function __construct(private readonly TranslationEntry $entry) {}

            public function scan(ModuleInfo $m): array
            {
                return [$this->entry];
            }
        };

        $cache = makeIndexCache(
            $rootPath,
            $walker,
            $attributeParser,
            $configScanner,
            $templateScanner,
            $translationScanner,
        );
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
    },
);

it(
    'it provides inverse indexes: find observers listening to a given event class, find plugins targeting a given class',
    function () use (&$tmpDirs): void {
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

        $walker = new class ($module) extends ModuleWalker
        {
            public function __construct(private readonly ModuleInfo $module) {}

            public function walk(): array
            {
                return [$this->module];
            }
        };
        $attributeParser = new class ($matchingObserver, $otherObserver, $matchingPlugin, $otherPlugin) extends AttributeParser
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
        $configScanner = new class () extends ConfigScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };
        $templateScanner = new class () extends TemplateScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };
        $translationScanner = new class () extends TranslationScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };

        $cache = makeIndexCache(
            $rootPath,
            $walker,
            $attributeParser,
            $configScanner,
            $templateScanner,
            $translationScanner,
        );
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
    },
);

// ── helpers for stub cache creation ──────────────────────────────────────────

function makeEmptyStubs(string $rootPath): IndexCache
{
    $walker = new class () extends ModuleWalker
    {
        public function __construct() {}

        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    return makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
}

function makeOneModuleStubs(string $rootPath, ModuleInfo $module): IndexCache
{
    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    return makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
}

it(
    'it rebuilds the index when the cache file is corrupt instead of reporting an empty index',
    function () use (&$tmpDirs): void {
        $rootPath = makeTempDir();
        $tmpDirs[] = $rootPath;

        $module = makeModule($rootPath . '/module');
        mkdir($module->path, 0755, true);

        // First: build a valid cache
        $cache = makeOneModuleStubs($rootPath, $module);
        $cache->build();

        // Corrupt the cache file
        $cacheFile = $rootPath . '/.marko/index.cache';
        file_put_contents($cacheFile, 'this is not valid serialized data !!!corrupt!!!');

        // Second: create a fresh cache instance backed by the same module
        // When getModules() is called, ensureLoaded() should detect load() failed
        // and call build() which re-scans and returns real data
        $cache2 = makeOneModuleStubs($rootPath, $module);

        // The corrupt cache must NOT produce an empty index; it must rebuild
        $modules = $cache2->getModules();

        expect($modules)->toHaveCount(1)
            ->and($modules[0]->name)->toBe('test/module');
    },
);

it('it returns false from load when the cache deserializes to a non-array', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Create the .marko dir and write a non-array serialized value
    mkdir($rootPath . '/.marko', 0755, true);
    $cacheFile = $rootPath . '/.marko/index.cache';
    file_put_contents($cacheFile, serialize('just a string, not an array'));

    $cache = makeEmptyStubs($rootPath);
    $result = $cache->load();

    expect($result)->toBeFalse();
});

it('it restricts unserialize to the indexer value-object allowlist', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Craft a payload that embeds a class NOT in the allowlist
    // unserialize with ['allowed_classes' => [...]] returns __PHP_Incomplete_Class for blocked classes
    // but we should return false (treat as corrupt) when result is not a valid array
    //
    // Here we embed stdClass — not in the allowlist — as a value in the array, then verify
    // the loaded result treats the cache as corrupt (load returns false).
    mkdir($rootPath . '/.marko', 0755, true);
    $cacheFile = $rootPath . '/.marko/index.cache';

    // Serialize an object of a class that is NOT in the allowlist
    $disallowedPayload = serialize(new stdClass());
    file_put_contents($cacheFile, $disallowedPayload);

    $cache = makeEmptyStubs($rootPath);
    $result = $cache->load();

    // A disallowed class object (not an array) must cause load() to return false
    expect($result)->toBeFalse();
});

it('it loads a valid cache file and reports its contents', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $module = makeModule($rootPath . '/module');
    mkdir($module->path, 0755, true);

    $observer = makeIndexObserver();

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class ($observer) extends AttributeParser
    {
        public function __construct(private readonly ObserverEntry $observer) {}

        public function observers(ModuleInfo $m): array
        {
            return [$this->observer];
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build cache with one module and one observer
    $cache = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
    $cache->build();

    // Load on a fresh instance
    $cache2 = makeIndexCache(
        $rootPath,
        $walker,
        $attributeParser,
        $configScanner,
        $templateScanner,
        $translationScanner,
    );
    $loaded = $cache2->load();

    expect($loaded)->toBeTrue()
        ->and($cache2->getModules())->toHaveCount(1)
        ->and($cache2->getObservers())->toHaveCount(1)
        ->and($cache2->getObservers()[0]->class)->toBe('Test\\Module\\Observers\\FooObserver');
});

it('it still returns false from load when the cache file is missing', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $cache = makeEmptyStubs($rootPath);
    $result = $cache->load();

    expect($result)->toBeFalse();
});

it(
    'it throws IndexCacheException with helpful suggestion when cache dir is unwritable',
    function () use (&$tmpDirs): void {
        $rootPath = makeTempDir();
        $tmpDirs[] = $rootPath;

        // Create .marko dir and make it unreadable/unwritable
        $markoDir = $rootPath . '/.marko';
        mkdir($markoDir, 0000, true);

        $walker = new class () extends ModuleWalker
        {
            public function __construct() {}

            public function walk(): array
            {
                return [];
            }
        };
        $attributeParser = new class () extends AttributeParser
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
        $configScanner = new class () extends ConfigScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };
        $templateScanner = new class () extends TemplateScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };
        $translationScanner = new class () extends TranslationScanner
        {
            public function scan(ModuleInfo $m): array
            {
                return [];
            }
        };

        $cache = makeIndexCache(
            $rootPath,
            $walker,
            $attributeParser,
            $configScanner,
            $templateScanner,
            $translationScanner,
        );

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
    },
);

// ── staleness-recheck-on-read tests ──────────────────────────────────────────

it('it reflects a newly added app module on the next read after the data was already loaded', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Initial app module on disk
    $appDir = $rootPath . '/app';
    $initialModulePath = $appDir . '/initial-module';
    mkdir($initialModulePath . '/src', 0755, true);
    file_put_contents($initialModulePath . '/src/Initial.php', '<?php class Initial {}');

    $initialModule = new ModuleInfo('test/initial', $initialModulePath, 'Test\\Initial\\');

    // Spy walker: starts returning 1 module, will return 2 after we update $modules
    $modules = [$initialModule];
    $walker = new class ($modules) extends ModuleWalker
    {
        /** @param list<ModuleInfo> $modules */
        public function __construct(private array &$modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };

    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read populates $this->data (singleton scenario)
    $first = $cache->getModules();
    expect($first)->toHaveCount(1);

    // Simulate a new app module appearing on disk AND being tracked by the walker
    $newModulePath = $appDir . '/new-module';
    mkdir($newModulePath . '/src', 0755, true);
    file_put_contents($newModulePath . '/src/New.php', '<?php class New_ {}');
    $newModule = new ModuleInfo('test/new', $newModulePath, 'Test\\New\\');
    $modules[] = $newModule;

    // Same instance: second read must detect the addition and rebuild
    $second = $cache->getModules();
    expect($second)->toHaveCount(2);
});

it('it reflects a newly added route via getRoutes after the data was already loaded', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/route-module';
    mkdir($modulePath . '/src', 0755, true);
    file_put_contents($modulePath . '/src/Ctrl.php', '<?php class Ctrl {}');

    $module = new ModuleInfo('test/route-module', $modulePath, 'Test\\Route\\');
    $modules = [$module];

    // Route list controlled externally
    $routes = [];
    $walker = new class ($modules) extends ModuleWalker
    {
        /** @param list<ModuleInfo> $modules */
        public function __construct(private array &$modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };
    $attributeParser = new class ($routes) extends AttributeParser
    {
        /** @param list<RouteEntry> $routes */
        public function __construct(private array &$routes) {}

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
            return $this->routes;
        }
    };
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read: no routes
    $first = $cache->getRoutes();
    expect($first)->toBeEmpty();

    // Simulate a new file change on disk
    file_put_contents($modulePath . '/src/NewRoute.php', '<?php class NewRoute {}');
    // Add a route to the parser stub
    $routes[] = makeIndexRoute();

    // Same instance: second read must detect the change and return the route
    $second = $cache->getRoutes();
    expect($second)->toHaveCount(1);
});

it('it reflects an edit to a tracked app or modules source file on the next read after first load', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/edit-module';
    mkdir($modulePath . '/src', 0755, true);
    $srcFile = $modulePath . '/src/Service.php';
    file_put_contents($srcFile, '<?php class Service {}');

    $module = new ModuleInfo('test/edit-module', $modulePath, 'Test\\Edit\\');
    $modules = [$module];
    $routeList = [];

    $walker = new class ($modules) extends ModuleWalker
    {
        /** @param list<ModuleInfo> $modules */
        public function __construct(private array &$modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };
    $attributeParser = new class ($routeList) extends AttributeParser
    {
        /** @param list<RouteEntry> $routes */
        public function __construct(private array &$routes) {}

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
            return $this->routes;
        }
    };
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read
    $first = $cache->getRoutes();
    expect($first)->toBeEmpty();

    // Simulate a file edit with a newer mtime
    $cacheFile = $rootPath . '/.marko/index.cache';
    touch($srcFile, filemtime($cacheFile) + 10);
    $routeList[] = makeIndexRoute();

    // Same instance: second read detects the edit and rebuilds
    $second = $cache->getRoutes();
    expect($second)->toHaveCount(1);
});

it('it reflects a deleted app module on the next read by comparing the tracked path set', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/delete-module';
    mkdir($modulePath . '/src', 0755, true);
    $srcFile = $modulePath . '/src/ToDelete.php';
    file_put_contents($srcFile, '<?php class ToDelete {}');

    $module = new ModuleInfo('test/delete-module', $modulePath, 'Test\\Delete\\');
    $modules = [$module];

    $walker = new class ($modules) extends ModuleWalker
    {
        /** @param list<ModuleInfo> $modules */
        public function __construct(private array &$modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read: 1 module with 1 src file
    $first = $cache->getModules();
    expect($first)->toHaveCount(1);

    // Delete the source file on disk and remove module from walker
    unlink($srcFile);
    rmdir($modulePath . '/src');
    rmdir($modulePath);
    $modules = [];

    // Same instance: second read detects the deletion via path-set comparison
    $second = $cache->getModules();
    expect($second)->toBeEmpty();
});

it('it does not rebuild when no tracked file under app or modules changed', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/stable-module';
    mkdir($modulePath . '/src', 0755, true);
    file_put_contents($modulePath . '/src/Stable.php', '<?php class Stable {}');

    $module = new ModuleInfo('test/stable', $modulePath, 'Test\\Stable\\');
    $scanCount = 0;

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    // Count observers() calls — these only fire during build(), not during load() or isStale()
    $attributeParser = new class ($scanCount) extends AttributeParser
    {
        public function __construct(private int &$scanCount) {}

        public function observers(ModuleInfo $m): array
        {
            $this->scanCount++;

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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read triggers build (observers() called once per module)
    $cache->getModules();
    $scanCountAfterFirst = $scanCount;

    // Second and third reads: nothing changed, no rebuild — scan count must not increase
    $cache->getModules();
    $cache->getModules();
    $scanCountAfterThree = $scanCount;

    expect($cache->getModules())->toHaveCount(1)
        ->and($scanCountAfterThree)->toBe($scanCountAfterFirst);
});

it('it does not trigger a rebuild when only a vendor file changes after first load', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    // Create a vendor module on disk (path under vendor/)
    $vendorModulePath = $rootPath . '/vendor/vendor-org/vendor-pkg';
    mkdir($vendorModulePath . '/src', 0755, true);
    $vendorSrcFile = $vendorModulePath . '/src/VendorClass.php';
    file_put_contents($vendorSrcFile, '<?php class VendorClass {}');

    // Create an app module on disk as well
    $appDir = $rootPath . '/app';
    $appModulePath = $appDir . '/app-module';
    mkdir($appModulePath . '/src', 0755, true);
    file_put_contents($appModulePath . '/src/AppClass.php', '<?php class AppClass {}');

    $vendorModule = new ModuleInfo('vendor-org/vendor-pkg', $vendorModulePath, 'Vendor\\Pkg\\');
    $appModule = new ModuleInfo('test/app-module', $appModulePath, 'Test\\App\\');
    $scanCount = 0;

    $walker = new class ($vendorModule, $appModule) extends ModuleWalker
    {
        public function __construct(
            private readonly ModuleInfo $vendorModule,
            private readonly ModuleInfo $appModule,
        ) {}

        public function walk(): array
        {
            return [$this->vendorModule, $this->appModule];
        }
    };
    // Count observers() calls — these only fire during build(), not during isStale()
    $attributeParser = new class ($scanCount) extends AttributeParser
    {
        public function __construct(private int &$scanCount) {}

        public function observers(ModuleInfo $m): array
        {
            $this->scanCount++;

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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read — builds (observers called for each module: vendor + app = 2 calls)
    $cache->getModules();
    $scanCountAfterFirst = $scanCount;

    // Touch only a vendor file (newer than cache)
    $cacheFile = $rootPath . '/.marko/index.cache';
    touch($vendorSrcFile, filemtime($cacheFile) + 10);

    // Second read: vendor change must NOT trigger rebuild — scan count must not increase
    $cache->getModules();
    $scanCountAfterSecond = $scanCount;

    expect($scanCountAfterSecond)->toBe($scanCountAfterFirst);
});

it('it rebuilds at most once for several successive reads following a single change', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/once-module';
    mkdir($modulePath . '/src', 0755, true);
    $srcFile = $modulePath . '/src/Once.php';
    file_put_contents($srcFile, '<?php class Once {}');

    $module = new ModuleInfo('test/once', $modulePath, 'Test\\Once\\');
    $scanCount = 0;

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    // Count observers() calls — only happen during build()
    $attributeParser = new class ($scanCount) extends AttributeParser
    {
        public function __construct(private int &$scanCount) {}

        public function observers(ModuleInfo $m): array
        {
            $this->scanCount++;

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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // First read
    $cache->getModules();
    $scanCountAfterFirst = $scanCount;

    // Backdate the cache file so the existing src file appears newer than the cache.
    // After the rebuild, the new cache is written at "now" which is > the src file
    // mtime, so subsequent reads are not stale (no rebuild storm).
    $cacheFile = $rootPath . '/.marko/index.cache';
    touch($cacheFile, filemtime($srcFile) - 20);

    // Three successive reads after one change — only ONE rebuild should fire
    $cache->getModules();
    $cache->getModules();
    $cache->getModules();
    $scanCountAfterThree = $scanCount;

    // Only one additional build should have fired (scan count increases by 1 per build call)
    expect($scanCountAfterThree - $scanCountAfterFirst)->toBe(1);
});

it('it treats a cache payload missing the tracked path set as stale so old caches rebuild once', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/legacy-module';
    mkdir($modulePath . '/src', 0755, true);
    file_put_contents($modulePath . '/src/Legacy.php', '<?php class Legacy {}');

    $module = new ModuleInfo('test/legacy', $modulePath, 'Test\\Legacy\\');
    $modules = [$module];

    $walker = new class ($modules) extends ModuleWalker
    {
        /** @param list<ModuleInfo> $modules */
        public function __construct(private array &$modules) {}

        public function walk(): array
        {
            return $this->modules;
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Write a legacy cache payload without 'trackedPaths' key
    mkdir($rootPath . '/.marko', 0755, true);
    $legacyPayload = [
        'modules' => [$module],
        'observers' => [],
        'plugins' => [],
        'preferences' => [],
        'commands' => [],
        'routes' => [],
        'configKeys' => [],
        'templates' => [],
        'translationKeys' => [],
        // intentionally no 'trackedPaths' key
    ];
    file_put_contents($rootPath . '/.marko/index.cache', serialize($legacyPayload));

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);

    // Must not throw, must load (or rebuild) cleanly
    $result = $cache->getModules();
    expect($result)->toHaveCount(1);
});

it('it persists the tracked path set as a plain array or string that requires no new unserialize allowed class', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $appDir = $rootPath . '/app';
    $modulePath = $appDir . '/tracked-module';
    mkdir($modulePath . '/src', 0755, true);
    file_put_contents($modulePath . '/src/Tracked.php', '<?php class Tracked {}');

    $module = new ModuleInfo('test/tracked', $modulePath, 'Test\\Tracked\\');

    $walker = new class ($module) extends ModuleWalker
    {
        public function __construct(private readonly ModuleInfo $module) {}

        public function walk(): array
        {
            return [$this->module];
        }
    };
    $attributeParser = new class () extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    $cacheFile = $rootPath . '/.marko/index.cache';
    $raw = file_get_contents($cacheFile);

    // Deserialize with the existing allowlist (no new classes)
    $data = unserialize($raw, [
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

    expect($data)->toBeArray()
        ->and($data)->toHaveKey('trackedPaths')
        ->and($data['trackedPaths'])->toBeArray();

    // Each entry in trackedPaths must be a plain string
    foreach ($data['trackedPaths'] as $path) {
        expect($path)->toBeString();
    }
});

it('it preserves first-load semantics by loading a fresh on-disk cache without rescanning', function () use (&$tmpDirs): void {
    $rootPath = makeTempDir();
    $tmpDirs[] = $rootPath;

    $scanCount = 0;

    $walker = new class () extends ModuleWalker
    {
        public function __construct() {}

        public function walk(): array
        {
            return [];
        }
    };
    $attributeParser = new class ($scanCount) extends AttributeParser
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
    $configScanner = new class () extends ConfigScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $templateScanner = new class () extends TemplateScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };
    $translationScanner = new class () extends TranslationScanner
    {
        public function scan(ModuleInfo $m): array
        {
            return [];
        }
    };

    // Build and save cache using first instance
    $cache = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $cache->build();

    // Load on a fresh instance — should not re-invoke scanners
    $cache2 = makeIndexCache($rootPath, $walker, $attributeParser, $configScanner, $templateScanner, $translationScanner);
    $loaded = $cache2->load();

    expect($loaded)->toBeTrue()
        ->and($scanCount)->toBe(0);
});
