<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginDefinition;

// Helper to create a DiscoveryCache pointed at a unique temp directory
function makeCacheSetup(string $cacheDir): array
{
    $cachePath = $cacheDir . '/discovery.php';
    $_ENV['DISCOVERY_CACHE_PATH'] = $cachePath;
    $paths = new ProjectPaths($cacheDir);
    $env = new DiscoveryEnvironment();

    return [
        'cache' => new DiscoveryCache($paths, $env),
        'path' => $cachePath,
        'dir' => $cacheDir,
    ];
}

function emptyPayload(): array
{
    return [
        'preferences' => [],
        'plugins' => [],
        'observers' => [],
        'commands' => [],
    ];
}

function samplePayload(): array
{
    return [
        'preferences' => [
            new PreferenceRecord(
                replacement: 'App\MyLogger',
                replaces: 'Marko\Core\Logger',
            ),
        ],
        'plugins' => [
            new PluginDefinition(
                pluginClass: 'App\PricePlugin',
                targetClass: 'App\Product',
                beforeMethods: [
                    'getPrice' => ['pluginMethod' => 'beforeGetPrice', 'sortOrder' => 10],
                ],
                afterMethods: [
                    'getPrice' => ['pluginMethod' => 'afterGetPrice', 'sortOrder' => 20],
                ],
            ),
        ],
        'observers' => [
            new ObserverDefinition(
                observerClass: 'App\OrderObserver',
                eventClass: 'App\OrderPlaced',
                priority: 5,
                async: true,
            ),
        ],
        'commands' => [
            new CommandDefinition(
                commandClass: 'App\CacheCommand',
                name: 'cache:clear',
                description: 'Clears the cache',
                aliases: ['cc'],
            ),
        ],
    ];
}

describe('DiscoveryCache', function (): void {
    beforeEach(function (): void {
        $this->tmpDir = sys_get_temp_dir() . '/marko_discovery_cache_test_' . uniqid('', true);
        $this->originalCachePath = array_key_exists('DISCOVERY_CACHE_PATH', $_ENV)
            ? $_ENV['DISCOVERY_CACHE_PATH']
            : null;
    });

    afterEach(function (): void {
        // Restore env
        if ($this->originalCachePath === null) {
            unset($_ENV['DISCOVERY_CACHE_PATH']);
        } else {
            $_ENV['DISCOVERY_CACHE_PATH'] = $this->originalCachePath;
        }

        // Clean up temp directory
        if (is_dir($this->tmpDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            rmdir($this->tmpDir);
        }
    });

    it(
        'writes a discovery payload to a return-array PHP file at the configured cache path and creates the directory if missing',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];
            $path = $setup['path'];

            expect(is_dir($this->tmpDir))->toBeFalse();

            $cache->write(emptyPayload());

            expect(file_exists($path))->toBeTrue()
                ->and(is_dir($this->tmpDir))->toBeTrue();

            $content = (string) file_get_contents($path);
            expect($content)->toStartWith('<?php')
                ->and($content)->toContain('return ');
        },
    );

    it(
        'reports exists() true after a write and false before any write or after clear',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];

            expect($cache->exists())->toBeFalse();

            $cache->write(emptyPayload());
            expect($cache->exists())->toBeTrue();

            $cache->clear();
            expect($cache->exists())->toBeFalse();
        },
    );

    it(
        'clears the cache file and clear() is idempotent when the file is already absent',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];

            // Should not throw when file doesn't exist
            $cache->clear();
            expect($cache->exists())->toBeFalse();

            // Write, clear, clear again — still no exception
            $cache->write(emptyPayload());
            $cache->clear();
            $cache->clear();
            expect($cache->exists())->toBeFalse();
        },
    );

    it(
        'loads a written cache back into PreferenceRecord, PluginDefinition, ObserverDefinition, and CommandDefinition objects identical to the payload',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];
            $payload = samplePayload();

            $cache->write($payload);
            $loaded = $cache->load();

            expect($loaded['preferences'])->toHaveCount(1)
                ->and($loaded['preferences'][0])->toBeInstanceOf(PreferenceRecord::class)
                ->and($loaded['preferences'][0]->replacement)->toBe('App\MyLogger')
                ->and($loaded['preferences'][0]->replaces)->toBe('Marko\Core\Logger')
                ->and($loaded['plugins'])->toHaveCount(1)
                ->and($loaded['plugins'][0])->toBeInstanceOf(PluginDefinition::class)
                ->and($loaded['plugins'][0]->pluginClass)->toBe('App\PricePlugin')
                ->and($loaded['plugins'][0]->targetClass)->toBe('App\Product')
                ->and($loaded['observers'])->toHaveCount(1)
                ->and($loaded['observers'][0])->toBeInstanceOf(ObserverDefinition::class)
                ->and($loaded['observers'][0]->observerClass)->toBe('App\OrderObserver')
                ->and($loaded['observers'][0]->eventClass)->toBe('App\OrderPlaced')
                ->and($loaded['observers'][0]->priority)->toBe(5)
                ->and($loaded['observers'][0]->async)->toBeTrue()
                ->and($loaded['commands'])->toHaveCount(1)
                ->and($loaded['commands'][0])->toBeInstanceOf(CommandDefinition::class)
                ->and($loaded['commands'][0]->commandClass)->toBe('App\CacheCommand')
                ->and($loaded['commands'][0]->name)->toBe('cache:clear')
                ->and($loaded['commands'][0]->description)->toBe('Clears the cache')
                ->and($loaded['commands'][0]->aliases)->toBe(['cc']);
        },
    );

    it(
        'round-trips PluginDefinition beforeMethods/afterMethods associative arrays preserving target-method keys and the pluginMethod/sortOrder shape',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];
            $before = [
                'getPrice' => ['pluginMethod' => 'beforeGetPrice', 'sortOrder' => 10],
                'getTitle' => ['pluginMethod' => 'beforeGetTitle', 'sortOrder' => 5],
            ];
            $after = [
                'getPrice' => ['pluginMethod' => 'afterGetPrice', 'sortOrder' => 20],
            ];
            $payload = [
                'preferences' => [],
                'plugins' => [
                    new PluginDefinition(
                        pluginClass: 'App\Plugin',
                        targetClass: 'App\Target',
                        beforeMethods: $before,
                        afterMethods: $after,
                    ),
                ],
                'observers' => [],
                'commands' => [],
            ];

            $cache->write($payload);
            $loaded = $cache->load();

            expect($loaded['plugins'][0]->beforeMethods)->toBe($before)
                ->and($loaded['plugins'][0]->afterMethods)->toBe($after);
        },
    );

    it(
        'round-trips empty definition arrays and empty aliases/beforeMethods/afterMethods without turning associative arrays into lists',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $cache = $setup['cache'];

            $cache->write(emptyPayload());
            $loaded = $cache->load();

            expect($loaded['preferences'])->toBeEmpty()
                ->and($loaded['plugins'])->toBeEmpty()
                ->and($loaded['observers'])->toBeEmpty()
                ->and($loaded['commands'])->toBeEmpty();
        },
    );

    it(
        'resolves a relative cache_path against the project base path and uses an absolute cache_path unchanged',
        function (): void {
            // Relative path: resolve against base
            $relPath = 'storage/cache/discovery.php';
            $_ENV['DISCOVERY_CACHE_PATH'] = $relPath;
            $paths = new ProjectPaths($this->tmpDir);
            $env = new DiscoveryEnvironment();
            $cacheRelative = new DiscoveryCache($paths, $env);

            $cacheRelative->write(emptyPayload());
            $expectedAbsPath = $this->tmpDir . '/' . $relPath;
            expect(file_exists($expectedAbsPath))->toBeTrue();

            // Absolute path: use as-is
            $absPath = $this->tmpDir . '/abs/discovery.php';
            $_ENV['DISCOVERY_CACHE_PATH'] = $absPath;
            $env2 = new DiscoveryEnvironment();
            $cacheAbsolute = new DiscoveryCache($paths, $env2);

            $cacheAbsolute->write(emptyPayload());
            expect(file_exists($absPath))->toBeTrue();
        },
    );

    it(
        'throws DiscoveryCacheException when the cache file content is not a PHP array',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];

            mkdir($this->tmpDir, 0755, true);
            file_put_contents($path, '<?php return "not an array";');

            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException when the cache file is missing required keys (version, preferences, plugins, observers, commands)',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];

            mkdir($this->tmpDir, 0755, true);
            file_put_contents($path, '<?php return ["version" => 1];');

            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException when the cache version key does not match the current cache schema version',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];

            mkdir($this->tmpDir, 0755, true);
            file_put_contents(
                $path,
                '<?php return ["version" => 9999, "preferences" => [], "plugins" => [], "observers" => [], "commands" => []];',
            );

            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException when a record within a section is missing a required field (e.g. a plugin entry without targetClass)',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];
            $version = DiscoveryCache::CACHE_VERSION;

            mkdir($this->tmpDir, 0755, true);
            file_put_contents(
                $path,
                "<?php return ['version' => $version, 'preferences' => [], 'plugins' => [['pluginClass' => 'App\\\\Plugin']], 'observers' => [], 'commands' => []];",
            );

            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException when a record field has the wrong type (e.g. observer priority is a string, command aliases is not an array, plugin beforeMethods is not an array)',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];
            $version = DiscoveryCache::CACHE_VERSION;

            mkdir($this->tmpDir, 0755, true);

            // Observer with priority as string
            file_put_contents(
                $path,
                "<?php return ['version' => $version, 'preferences' => [], 'plugins' => [], 'observers' => [['observerClass' => 'App\\\\Obs', 'eventClass' => 'App\\\\Evt', 'priority' => 'high', 'async' => false]], 'commands' => []];",
            );
            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);

            // Command aliases as string instead of array
            file_put_contents(
                $path,
                "<?php return ['version' => $version, 'preferences' => [], 'plugins' => [], 'observers' => [], 'commands' => [['commandClass' => 'App\\\\Cmd', 'name' => 'cmd', 'description' => '', 'aliases' => 'not-array']]];",
            );
            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);

            // Plugin beforeMethods as string
            file_put_contents(
                $path,
                "<?php return ['version' => $version, 'preferences' => [], 'plugins' => [['pluginClass' => 'App\\\\P', 'targetClass' => 'App\\\\T', 'beforeMethods' => 'wrong', 'afterMethods' => []]], 'observers' => [], 'commands' => []];",
            );
            expect(fn () => $cache->load())->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException::notWritable when the target directory cannot be created or the file/rename cannot be written (never returns false silently)',
        function (): void {
            // Point to a path under a non-writable location
            $unwritable = '/root/no_access_' . uniqid('', true) . '/discovery.php';
            $_ENV['DISCOVERY_CACHE_PATH'] = $unwritable;
            $paths = new ProjectPaths($this->tmpDir);
            $env = new DiscoveryEnvironment();
            $cache = new DiscoveryCache($paths, $env);

            expect(fn () => $cache->write(emptyPayload()))->toThrow(DiscoveryCacheException::class);
        },
    );

    it(
        'throws DiscoveryCacheException whose suggestion names the cache file path to delete as the primary recovery, plus discovery:clear, when content is corrupt',
        function (): void {
            $setup = makeCacheSetup($this->tmpDir);
            $path = $setup['path'];
            $cache = $setup['cache'];

            mkdir($this->tmpDir, 0755, true);
            file_put_contents($path, '<?php return "corrupt";');

            $exception = null;
            try {
                $cache->load();
            } catch (DiscoveryCacheException $e) {
                $exception = $e;
            }

            expect($exception)->not->toBeNull()
                ->and($exception->getSuggestion())->toContain($path)
                ->and($exception->getSuggestion())->toContain('discovery:clear');
        },
    );
});
