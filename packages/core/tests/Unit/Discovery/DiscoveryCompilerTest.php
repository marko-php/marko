<?php

declare(strict_types=1);

use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryCompiler;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginDiscovery;

// Helper to create a temp module directory with an src/ folder
function makeCompilerTestModule(string $name): array
{
    $tempDir = sys_get_temp_dir() . '/marko_compiler_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src', 0755, true);

    file_put_contents($tempDir . '/composer.json', json_encode([
        'name' => $name,
        'extra' => ['marko' => ['module' => true]],
    ]));

    return [
        'dir' => $tempDir,
        'manifest' => new ModuleManifest(
            name: $name,
            version: '1.0.0',
            path: $tempDir,
        ),
    ];
}

// Recursively remove a directory
function compilerTestCleanup(string $dir): void
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
            compilerTestCleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

describe('DiscoveryCompiler', function (): void {
    beforeEach(function (): void {
        $this->originalCachePath = array_key_exists('DISCOVERY_CACHE_PATH', $_ENV)
            ? $_ENV['DISCOVERY_CACHE_PATH']
            : null;
    });

    afterEach(function (): void {
        if ($this->originalCachePath === null) {
            unset($_ENV['DISCOVERY_CACHE_PATH']);
        } else {
            $_ENV['DISCOVERY_CACHE_PATH'] = $this->originalCachePath;
        }
    });

    it(
        'compiles an empty payload (empty preference, plugin, observer, command arrays) for modules with no attribute-bearing classes',
        function (): void {
            $module = makeCompilerTestModule('test/empty-module');
            $compiler = new DiscoveryCompiler();

            $payload = $compiler->compile([$module['manifest']]);

            expect($payload)->toBeArray()
                ->and($payload['preferences'])->toBeEmpty()
                ->and($payload['plugins'])->toBeEmpty()
                ->and($payload['observers'])->toBeEmpty()
                ->and($payload['commands'])->toBeEmpty();

            compilerTestCleanup($module['dir']);
        },
    );

    it(
        'includes the current cache schema version key sourced from DiscoveryCache::CACHE_VERSION (not a hardcoded literal) in the compiled payload',
        function (): void {
            $module = makeCompilerTestModule('test/version-module');
            $compiler = new DiscoveryCompiler();

            $payload = $compiler->compile([$module['manifest']]);

            expect($payload)->toHaveKey('version')
                ->and($payload['version'])->toBe(DiscoveryCache::CACHE_VERSION);

            compilerTestCleanup($module['dir']);
        },
    );

    it(
        'compiles preferences discovered across all modules into the payload preferences array',
        function (): void {
            $module = makeCompilerTestModule('test/pref-module');

            // Write a PHP file with a #[Preference] class into the module's src/
            $preferenceCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\Pref;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: 'DiscoveryCompilerTest\Pref\OriginalService')]
class ReplacementService
{
}
PHP;
            file_put_contents($module['dir'] . '/src/ReplacementService.php', $preferenceCode);

            $compiler = new DiscoveryCompiler();
            $payload = $compiler->compile([$module['manifest']]);

            expect($payload['preferences'])->toHaveCount(1)
                ->and($payload['preferences'][0]->replacement)->toBe('DiscoveryCompilerTest\\Pref\\ReplacementService')
                ->and($payload['preferences'][0]->replaces)->toBe('DiscoveryCompilerTest\\Pref\\OriginalService');

            compilerTestCleanup($module['dir']);
        },
    );

    it(
        'compiles plugins, observers, and commands discovered across all modules into their respective payload arrays',
        function (): void {
            $module = makeCompilerTestModule('test/all-types-module');

            // Plugin class
            $pluginCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\AllTypes;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: 'DiscoveryCompilerTest\AllTypes\TargetService')]
class ServicePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(sortOrder: 10)]
    public function beforeProcess(): void {}

    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(sortOrder: 20)]
    public function afterProcess(): void {}
}
PHP;
            file_put_contents($module['dir'] . '/src/ServicePlugin.php', $pluginCode);

            // Observer class
            $observerCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\AllTypes;

use Marko\Core\Attributes\Observer;

#[Observer(event: 'DiscoveryCompilerTest\AllTypes\TestEvent', priority: 5)]
class TestObserver
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function handle(object $event): void {}
}
PHP;
            file_put_contents($module['dir'] . '/src/TestObserver.php', $observerCode);

            // Command class
            $commandCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\AllTypes;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'test:run', description: 'Run test command', aliases: ['tr'])]
class TestCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        return 0;
    }
}
PHP;
            file_put_contents($module['dir'] . '/src/TestCommand.php', $commandCode);

            $compiler = new DiscoveryCompiler();
            $payload = $compiler->compile([$module['manifest']]);

            expect($payload['plugins'])->toHaveCount(1)
                ->and($payload['plugins'][0]->pluginClass)->toBe('DiscoveryCompilerTest\\AllTypes\\ServicePlugin')
                ->and($payload['plugins'][0]->targetClass)->toBe('DiscoveryCompilerTest\\AllTypes\\TargetService')
                ->and($payload['observers'])->toHaveCount(1)
                ->and($payload['observers'][0]->observerClass)->toBe('DiscoveryCompilerTest\\AllTypes\\TestObserver')
                ->and($payload['observers'][0]->eventClass)->toBe('DiscoveryCompilerTest\\AllTypes\\TestEvent')
                ->and($payload['observers'][0]->priority)->toBe(5)
                ->and($payload['commands'])->toHaveCount(1)
                ->and($payload['commands'][0]->commandClass)->toBe('DiscoveryCompilerTest\\AllTypes\\TestCommand')
                ->and($payload['commands'][0]->name)->toBe('test:run')
                ->and($payload['commands'][0]->aliases)->toBe(['tr']);

            compilerTestCleanup($module['dir']);
        },
    );

    it(
        'produces a payload that, written and reloaded through DiscoveryCache, yields discovery objects identical to running the four discovery passes directly over the same modules',
        function (): void {
            $module = makeCompilerTestModule('test/equivalence-module');
            $cacheDir = sys_get_temp_dir() . '/marko_compiler_cache_' . bin2hex(random_bytes(8));

            // Write a preference class
            $preferenceCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\Equiv;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: 'DiscoveryCompilerTest\Equiv\OriginalSvc')]
class ReplacementSvc
{
}
PHP;
            file_put_contents($module['dir'] . '/src/ReplacementSvc.php', $preferenceCode);

            // Build the cache
            $cachePath = $cacheDir . '/discovery.php';
            $_ENV['DISCOVERY_CACHE_PATH'] = $cachePath;
            $paths = new ProjectPaths($cacheDir);
            $env = new DiscoveryEnvironment();
            $cache = new DiscoveryCache($paths, $env);

            $compiler = new DiscoveryCompiler();
            $payload = $compiler->compile([$module['manifest']]);
            $cache->write($payload);
            $loaded = $cache->load();

            // Run the four discovery passes directly (same construction as Application)
            $preferenceDiscovery = new PreferenceDiscovery();
            $directPreferences = $preferenceDiscovery->discoverInModule($module['manifest']);

            $pluginDiscovery = new PluginDiscovery();
            $directPlugins = $pluginDiscovery->discoverInModule($module['manifest']);

            $observerDiscovery = new ObserverDiscovery(new ClassFileParser());
            $directObservers = $observerDiscovery->discover([$module['manifest']]);

            $commandDiscovery = new CommandDiscovery(new ClassFileParser());
            $directCommands = $commandDiscovery->discover([$module['manifest']]);

            expect($loaded['preferences'])->toHaveCount(count($directPreferences))
                ->and($loaded['plugins'])->toHaveCount(count($directPlugins))
                ->and($loaded['observers'])->toHaveCount(count($directObservers))
                ->and($loaded['commands'])->toHaveCount(count($directCommands));

            // Verify preference round-trips correctly
            expect($loaded['preferences'][0]->replacement)->toBe($directPreferences[0]->replacement)
                ->and($loaded['preferences'][0]->replaces)->toBe($directPreferences[0]->replaces);

            compilerTestCleanup($module['dir']);
            compilerTestCleanup($cacheDir);

            unset($_ENV['DISCOVERY_CACHE_PATH']);
        },
    );

    it(
        'aggregates results from multiple modules preserving the module load order',
        function (): void {
            $moduleA = makeCompilerTestModule('test/module-a');
            $moduleB = makeCompilerTestModule('test/module-b');

            // Module A: preference
            $prefA = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\MultiA;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: 'DiscoveryCompilerTest\MultiA\OriginalA')]
class ReplacementA
{
}
PHP;
            file_put_contents($moduleA['dir'] . '/src/ReplacementA.php', $prefA);

            // Module B: preference
            $prefB = <<<'PHP'
<?php

declare(strict_types=1);

namespace DiscoveryCompilerTest\MultiB;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: 'DiscoveryCompilerTest\MultiB\OriginalB')]
class ReplacementB
{
}
PHP;
            file_put_contents($moduleB['dir'] . '/src/ReplacementB.php', $prefB);

            $compiler = new DiscoveryCompiler();
            // Pass modules in A-then-B order
            $payload = $compiler->compile([$moduleA['manifest'], $moduleB['manifest']]);

            expect($payload['preferences'])->toHaveCount(2)
                ->and($payload['preferences'][0]->replacement)->toBe('DiscoveryCompilerTest\\MultiA\\ReplacementA')
                ->and($payload['preferences'][1]->replacement)->toBe('DiscoveryCompilerTest\\MultiB\\ReplacementB');

            compilerTestCleanup($moduleA['dir']);
            compilerTestCleanup($moduleB['dir']);
        },
    );
});
