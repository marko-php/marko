<?php

declare(strict_types=1);

use Marko\Core\Application;
use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginDefinition;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Snapshot + restore the three env keys used by the cache gate.
 * Returns a closure that restores the original values.
 */
function cacheTestSnapshotEnv(): Closure
{
    $keys = ['APP_ENV', 'DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'];
    $saved = [];

    foreach ($keys as $key) {
        $saved[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
    }

    return function () use ($keys, $saved): void {
        foreach ($keys as $key) {
            if ($saved[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $saved[$key];
            }
        }
    };
}

/**
 * Recursively delete a directory and all its contents.
 */
function cacheTestCleanupDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cacheTestCleanupDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Write a valid discovery cache file at the default path under $basePath.
 *
 * @param array{preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[]} $payload
 */
function cacheTestWriteCache(string $basePath, array $payload): void
{
    $projectPaths = new ProjectPaths($basePath);
    $env = new DiscoveryEnvironment();
    $cache = new DiscoveryCache($projectPaths, $env);
    $cache->write($payload);
}

/**
 * Write a corrupt (non-PHP-return-array) cache file so DiscoveryCache::load() throws.
 */
function cacheTestWriteCorruptCache(string $basePath): void
{
    $cachePath = $basePath . '/storage/cache/discovery.php';
    $dir = dirname($cachePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($cachePath, '<?php return "not an array";');
}

/**
 * Create a minimal Marko module directory (composer.json only, no src/).
 */
function cacheTestCreateModule(string $path, string $name): void
{
    mkdir($path, 0755, true);
    file_put_contents($path . '/composer.json', json_encode([
        'name' => $name,
        'version' => '1.0.0',
        'extra' => ['marko' => ['module' => true]],
    ], JSON_PRETTY_PRINT));
}

/**
 * Create a module with a command class in src/.
 * Returns the FQCN of the command class and its CLI name attribute value.
 *
 * @return array{class: string, commandName: string}
 */
function cacheTestCreateCommandModule(string $modulePath, string $moduleName, string $uniqueId): array
{
    cacheTestCreateModule($modulePath, $moduleName);
    mkdir($modulePath . '/src', 0755, true);

    $ns = "CacheTestCmd$uniqueId";
    $commandName = "cache:test-$uniqueId";

    $code = <<<PHP
<?php

declare(strict_types=1);

namespace $ns;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: '$commandName', description: 'Cache test command')]
class CachedCommand implements CommandInterface
{
    public function execute(
        Input \$input,
        Output \$output,
    ): int {
        return 0;
    }
}
PHP;
    file_put_contents($modulePath . '/src/CachedCommand.php', $code);

    return ['class' => "$ns\\CachedCommand", 'commandName' => $commandName];
}

/**
 * Build an empty valid payload (no definitions in any subsystem).
 *
 * @return array{preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[]}
 */
function cacheTestEmptyPayload(): array
{
    return [
        'preferences' => [],
        'plugins' => [],
        'observers' => [],
        'commands' => [],
    ];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it(
    'hydrates preferences, plugins, observers, and commands from the cache when enabled and environment is not development and the cache exists',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';
            cacheTestCreateModule($vendorDir . '/acme/core', 'acme/core');

            // Use real Application class as a plugin target (it definitely exists)
            $prefRecord = new PreferenceRecord(
                replacement: 'Acme\\Replacement',
                replaces: 'Acme\\Original',
            );
            $observerDef = new ObserverDefinition(
                observerClass: 'Acme\\Observer',
                eventClass: 'Acme\\Event',
                priority: 0,
                async: false,
            );
            $commandDef = new CommandDefinition(
                commandClass: 'Acme\\Cmd',
                name: 'acme:hydrate-cmd',
                description: 'Test',
                aliases: [],
            );

            // Use Application as a real target class for the plugin (so ReflectionClass succeeds)
            $pluginDef = new PluginDefinition(
                pluginClass: Application::class,
                targetClass: Application::class,
                beforeMethods: [],
                afterMethods: [],
            );

            $payload = [
                'preferences' => [$prefRecord],
                'plugins' => [$pluginDef],
                'observers' => [$observerDef],
                'commands' => [$commandDef],
            ];

            cacheTestWriteCache($baseDir, $payload);

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            // Preferences hydrated
            expect($app->preferenceRegistry->getPreference('Acme\\Original'))
                ->toBe('Acme\\Replacement')
                // Plugins hydrated — Application class is the target
                ->and($app->pluginRegistry->hasPluginsFor(Application::class))->toBeTrue()
                // Observers hydrated
                ->and($app->observerRegistry->getObserversFor('Acme\\Event'))->toHaveCount(1)
                // Commands hydrated
                ->and($app->commandRegistry->has('acme:hydrate-cmd'))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'binds CommandRegistry in the container and creates the CommandRunner on a cache hit (commands are runnable without a scan)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';
            cacheTestCreateModule($vendorDir . '/acme/core', 'acme/core');

            // Write an empty cache — we only need to confirm the wiring
            cacheTestWriteCache($baseDir, cacheTestEmptyPayload());

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            // CommandRegistry must be bound in the container
            $resolved = $app->container->get(CommandRegistry::class);

            expect($app->commandRegistry)->toBeInstanceOf(CommandRegistry::class)
                ->and($app->commandRunner)->toBeInstanceOf(CommandRunner::class)
                ->and($resolved)->toBe($app->commandRegistry);

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'registers each cached command exactly once and never throws duplicateCommandName on a cache hit (scan and cache halves are mutually exclusive)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // Build a cache that lists the same command that is also on disk
            $payload = [
                'preferences' => [],
                'plugins' => [],
                'observers' => [],
                'commands' => [
                    new CommandDefinition(
                        commandClass: $info['class'],
                        name: $info['commandName'],
                        description: 'Cache test command',
                        aliases: [],
                    ),
                ],
            ];
            cacheTestWriteCache($baseDir, $payload);

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );

            // Must not throw CommandException::duplicateCommandName
            $app->initialize();

            $all = $app->commandRegistry->all();
            $count = count(array_filter($all, fn ($d) => $d->name === $info['commandName']));

            expect($count)->toBe(1);

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'preserves the existing boot ordering on a cache hit (observers registered before the event dispatcher is created; commands after the module repository is bound)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';
            cacheTestCreateModule($vendorDir . '/acme/core', 'acme/core');

            $observerDef = new ObserverDefinition(
                observerClass: 'Acme\\Observer',
                eventClass: 'Acme\\Event',
                priority: 0,
                async: false,
            );
            $commandDef = new CommandDefinition(
                commandClass: 'Acme\\Cmd',
                name: 'acme:order-cmd',
                description: 'Test',
                aliases: [],
            );

            cacheTestWriteCache($baseDir, [
                'preferences' => [],
                'plugins' => [],
                'observers' => [$observerDef],
                'commands' => [$commandDef],
            ]);

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            // EventDispatcher was created AFTER observers — so observers must be registered
            $observers = $app->observerRegistry->getObserversFor('Acme\\Event');

            // CommandRegistry must be populated (commands ran after module-repo wiring)
            expect($observers)->toHaveCount(1)
                ->and($app->commandRegistry->has('acme:order-cmd'))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'does not consult the filesystem for markers on a cache hit (a class present on disk but absent from the cache is not registered)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // Write an EMPTY cache — the command exists on disk but is intentionally absent
            cacheTestWriteCache($baseDir, cacheTestEmptyPayload());

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            // The command is on disk but omitted from the cache → must NOT be registered
            expect($app->commandRegistry->has($info['commandName']))->toBeFalse();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'always rescans the filesystem in development even when a cache file exists (a class present on disk is registered regardless of cache contents)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // Write an EMPTY cache — command is on disk but absent from cache
            cacheTestWriteCache($baseDir, cacheTestEmptyPayload());

            // In development the cache must be ignored → rescan → command is found
            $_ENV['APP_ENV'] = 'development';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            expect($app->commandRegistry->has($info['commandName']))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'rescans the filesystem when caching is enabled but no cache file exists',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // No cache file written — rescan must happen
            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            expect($app->commandRegistry->has($info['commandName']))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'rescans the filesystem when caching is disabled by env (DISCOVERY_CACHE_ENABLED=false)',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // Write a cache that omits the command
            cacheTestWriteCache($baseDir, cacheTestEmptyPayload());

            // Disabled → rescan must occur → command is found despite empty cache
            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = 'false';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            expect($app->commandRegistry->has($info['commandName']))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'throws DiscoveryCacheException during boot when the cache exists but is corrupt and caching is enabled outside development',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';
            cacheTestCreateModule($vendorDir . '/acme/core', 'acme/core');

            cacheTestWriteCorruptCache($baseDir);

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );

            expect(fn () => $app->initialize())->toThrow(DiscoveryCacheException::class);

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'skips the corrupt-cache throw in development (rescans instead) so a corrupt cache never blocks dev boot',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            cacheTestWriteCorruptCache($baseDir);

            // Development mode: corrupt cache must be ignored, rescan proceeds
            $_ENV['APP_ENV'] = 'development';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            expect($app->commandRegistry->has($info['commandName']))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'produces the same registered preferences, plugins, observers, and commands from a valid cache as from a fresh scan of the same modules',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));

            // --- SCAN boot (development, no cache) ---
            $scanBaseDir = sys_get_temp_dir() . '/marko-cache-scan-' . $uniqueId;
            $scanVendorDir = $scanBaseDir . '/vendor';
            $scanInfo = cacheTestCreateCommandModule(
                $scanVendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            $_ENV['APP_ENV'] = 'development';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $scanApp = new Application(
                vendorPath: $scanVendorDir,
                modulesPath: '',
                appPath: '',
            );
            $scanApp->initialize();

            $scanCommands = $scanApp->commandRegistry->all();

            // --- CACHE boot (production, pre-written cache matching the scan) ---
            $cacheBaseDir = sys_get_temp_dir() . '/marko-cache-hit-' . $uniqueId;
            $cacheVendorDir = $cacheBaseDir . '/vendor';
            cacheTestCreateModule($cacheVendorDir . '/acme/core', 'acme/core');

            // Build cache payload mirroring what scan would produce
            $commandDef = new CommandDefinition(
                commandClass: $scanInfo['class'],
                name: $scanInfo['commandName'],
                description: 'Cache test command',
                aliases: [],
            );
            cacheTestWriteCache($cacheBaseDir, [
                'preferences' => [],
                'plugins' => [],
                'observers' => [],
                'commands' => [$commandDef],
            ]);

            $_ENV['APP_ENV'] = 'production';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = '1';

            $cacheApp = new Application(
                vendorPath: $cacheVendorDir,
                modulesPath: '',
                appPath: '',
            );
            $cacheApp->initialize();

            $cacheCommands = $cacheApp->commandRegistry->all();

            // Both paths must produce the same command names
            $scanNames = array_map(fn ($d) => $d->name, $scanCommands);
            $cacheNames = array_map(fn ($d) => $d->name, $cacheCommands);
            sort($scanNames);
            sort($cacheNames);

            expect($cacheNames)->toBe($scanNames);

            cacheTestCleanupDirectory($scanBaseDir);
            cacheTestCleanupDirectory($cacheBaseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'rescans (defaults to enabled production) when marko/env is not installed and no env vars are set',
    function (): void {
        $restore = cacheTestSnapshotEnv();

        try {
            $uniqueId = bin2hex(random_bytes(8));
            $baseDir = sys_get_temp_dir() . '/marko-cache-test-' . $uniqueId;
            $vendorDir = $baseDir . '/vendor';

            $info = cacheTestCreateCommandModule(
                $vendorDir . '/acme/core',
                'acme/core',
                $uniqueId,
            );

            // No env vars set, no cache file → defaults to enabled+production → $cache->exists() is false → rescan
            unset($_ENV['APP_ENV'], $_ENV['DISCOVERY_CACHE_ENABLED'], $_ENV['DISCOVERY_CACHE_PATH']);

            $app = new Application(
                vendorPath: $vendorDir,
                modulesPath: '',
                appPath: '',
            );
            $app->initialize();

            // Rescan ran, command is found
            expect($app->commandRegistry->has($info['commandName']))->toBeTrue();

            cacheTestCleanupDirectory($baseDir);
        } finally {
            $restore();
        }
    },
);

it(
    'restores $_ENV and removes any temp cache file after each test so other Application::initialize() tests are unaffected',
    function (): void {
        // This is a meta-test confirming the restore helper works correctly.
        $restore = cacheTestSnapshotEnv();

        $originalAppEnv = $_ENV['APP_ENV'] ?? null;
        $originalEnabled = $_ENV['DISCOVERY_CACHE_ENABLED'] ?? null;

        try {
            $_ENV['APP_ENV'] = 'staging';
            $_ENV['DISCOVERY_CACHE_ENABLED'] = 'false';

            // Verify mutation happened
            expect($_ENV['APP_ENV'])->toBe('staging')
                ->and($_ENV['DISCOVERY_CACHE_ENABLED'])->toBe('false');
        } finally {
            $restore();
        }

        // After restore the env must match what was there before
        $restoredAppEnv = $_ENV['APP_ENV'] ?? null;
        $restoredEnabled = $_ENV['DISCOVERY_CACHE_ENABLED'] ?? null;

        expect($restoredAppEnv)->toBe($originalAppEnv)
            ->and($restoredEnabled)->toBe($originalEnabled);
    },
);
