<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Discovery\ConfigCacheContributor;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;

/**
 * Build a container wired with the config module's module.php and a project
 * whose root config directory holds a file that counts how often it is loaded.
 */
function configModuleContainer(string $basePath): Container
{
    $moduleConfig = require __DIR__ . '/../../module.php';

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ProjectPaths::class, new ProjectPaths($basePath));
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));

    (new BindingRegistry($container))->registerModule(new ModuleManifest(
        name: 'marko/config',
        version: '1.0.0',
        bindings: $moduleConfig['bindings'],
        singletons: $moduleConfig['singletons'] ?? [],
    ));

    return $container;
}

beforeEach(function (): void {
    $this->basePath = sys_get_temp_dir() . '/marko-config-singleton-' . bin2hex(random_bytes(8));
    mkdir($this->basePath . '/config', 0755, true);
    file_put_contents(
        $this->basePath . '/config/counter.php',
        "<?php\n\$GLOBALS['marko_config_counter_loads'] = (\$GLOBALS['marko_config_counter_loads'] ?? 0) + 1;\n\nreturn ['value' => 'loaded'];\n",
    );
    $GLOBALS['marko_config_counter_loads'] = 0;
});

afterEach(function (): void {
    unset($GLOBALS['marko_config_counter_loads']);
    unlink($this->basePath . '/config/counter.php');
    rmdir($this->basePath . '/config');
    rmdir($this->basePath);
});

it('module.php binds ConfigRepositoryInterface to a factory closure', function () {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['bindings'])->toHaveKey(ConfigRepositoryInterface::class)
        ->and($moduleConfig['bindings'][ConfigRepositoryInterface::class])->toBeInstanceOf(Closure::class);
});

it('marks ConfigRepositoryInterface as a singleton', function (): void {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig)->toHaveKey('singletons')
        ->and($moduleConfig['singletons'])->toContain(ConfigRepositoryInterface::class);
});

it('returns the same repository instance when resolved twice', function (): void {
    $container = configModuleContainer($this->basePath);

    $first = $container->get(ConfigRepositoryInterface::class);

    expect($container->get(ConfigRepositoryInterface::class))->toBe($first)
        ->and($first->get('counter.value'))->toBe('loaded');
});

it('loads config files only once across resolves', function (): void {
    $container = configModuleContainer($this->basePath);

    $container->get(ConfigRepositoryInterface::class);
    $container->get(ConfigRepositoryInterface::class);
    $container->get(ConfigRepositoryInterface::class);

    expect($GLOBALS['marko_config_counter_loads'])->toBe(1);
});

it('declares the config discovery cache contributor', function (): void {
    $moduleConfig = require __DIR__ . '/../../module.php';

    expect($moduleConfig['discovery'] ?? [])->toBe([ConfigCacheContributor::class]);
})->issue(556);

it('builds the repository from the cached config section without loading any config file', function (): void {
    $container = configModuleContainer($this->basePath);
    $container->instance(CachedDiscovery::class, new CachedDiscovery([
        ConfigCacheContributor::KEY => ['values' => ['counter' => ['value' => 'cached']], 'env' => []],
    ], $this->basePath . '/storage/cache/discovery.php'));

    expect($container->get(ConfigRepositoryInterface::class)->get('counter.value'))->toBe('cached')
        ->and($GLOBALS['marko_config_counter_loads'])->toBe(0);
})->issue(556);

it('loads config files live when the boot did not use the discovery cache', function (): void {
    $container = configModuleContainer($this->basePath);
    $container->instance(CachedDiscovery::class, new CachedDiscovery());

    expect($container->get(ConfigRepositoryInterface::class)->get('counter.value'))->toBe('loaded')
        ->and($GLOBALS['marko_config_counter_loads'])->toBe(1);
})->issue(556);
