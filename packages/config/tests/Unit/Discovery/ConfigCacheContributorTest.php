<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigServiceProvider;
use Marko\Config\Discovery\ConfigCacheContributor;
use Marko\Config\Env;
use Marko\Config\Exceptions\ConfigCacheException;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;

/**
 * Write a config file into a temp project, creating its directory.
 */
function writeConfigCacheFixture(
    string $path,
    string $php,
): void {
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n\nuse Marko\\Config\\Env;\n\n" . $php . "\n");
}

function removeConfigCacheFixture(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

function configCacheDiscovery(): ConfigDiscovery
{
    return new ConfigDiscovery(new ConfigLoader(), new ConfigMerger());
}

beforeEach(function (): void {
    $this->base = sys_get_temp_dir() . '/marko-config-cache-' . bin2hex(random_bytes(6));
    $this->modulePath = $this->base . '/modules/acme/shop';
    $this->modules = [new ModuleManifest(name: 'acme/shop', version: '1.0.0', path: $this->modulePath)];
    $this->contributor = new ConfigCacheContributor(
        discovery: configCacheDiscovery(),
        loader: new ConfigLoader(),
        projectPaths: new ProjectPaths($this->base),
    );

    unset($_ENV['MARKO_CONFIG_CACHE_HOST'], $_ENV['MARKO_CONFIG_CACHE_PORT']);

    writeConfigCacheFixture(
        $this->modulePath . '/config/database.php',
        "return ['host' => 'localhost', 'port' => Env::int('MARKO_CONFIG_CACHE_PORT', 3306), 'name' => 'shop'];",
    );
    writeConfigCacheFixture(
        $this->base . '/config/database.php',
        "return ['host' => Env::string('MARKO_CONFIG_CACHE_HOST', 'db.internal')];",
    );
});

afterEach(function (): void {
    unset($_ENV['MARKO_CONFIG_CACHE_HOST'], $_ENV['MARKO_CONFIG_CACHE_PORT']);

    if (Env::isRecording()) {
        Env::stopRecording();
    }

    removeConfigCacheFixture($this->base);
});

it('uses the config section key', function (): void {
    expect($this->contributor->key())->toBe('config')
        ->and(ConfigCacheContributor::KEY)->toBe('config');
})->issue(556);

it('compiles the same merged config as live discovery over the module and root config directories', function (): void {
    $section = $this->contributor->compile($this->modules);

    expect($section['values'])->toBe(configCacheDiscovery()->discover([$this->modulePath], $this->base . '/config'))
        ->and($section['values'])->toBe(['database' => ['host' => 'db.internal', 'port' => 3306, 'name' => 'shop']]);
})->issue(556);

it('stores a hash of every environment variable the config files read through Env', function (): void {
    $section = $this->contributor->compile($this->modules);

    expect($section['env'])->toBe(Env::valueHashes(['MARKO_CONFIG_CACHE_PORT', 'MARKO_CONFIG_CACHE_HOST']))
        ->and(Env::isRecording())->toBeFalse();
})->issue(556);

it('fails naming the config key and the file when a config value is a closure', function (): void {
    $file = $this->base . '/config/database.php';
    writeConfigCacheFixture($file, "return ['connections' => ['main' => ['resolver' => fn () => 'x']]];");

    expect(fn () => $this->contributor->compile($this->modules))
        ->toThrow(function (ConfigCacheException $exception) use ($file): void {
            expect($exception)->toBeInstanceOf(DiscoveryCacheException::class)
                ->and($exception->getMessage())->toContain("'database.connections.main.resolver'")
                ->and($exception->getMessage())->toContain('Closure')
                ->and($exception->getMessage())->toContain($file);
        });

    expect(Env::isRecording())->toBeFalse();
})->issue(556);

it('names the module file an object came from when no later file overrides it', function (): void {
    $file = $this->modulePath . '/config/mail.php';
    writeConfigCacheFixture($file, "return ['transport' => new ArrayObject()];");

    expect(fn () => $this->contributor->compile($this->modules))
        ->toThrow(ConfigCacheException::class, "'mail.transport' in $file is a ArrayObject");
})->issue(556);

it('accepts a closure in a module config file that the root config replaces with a scalar', function (): void {
    writeConfigCacheFixture($this->modulePath . '/config/cache.php', "return ['store' => fn () => 'x'];");
    writeConfigCacheFixture($this->base . '/config/cache.php', "return ['store' => 'redis'];");

    expect($this->contributor->compile($this->modules)['values']['cache'])->toBe(['store' => 'redis']);
})->issue(556);

it('boots from the compiled section while the environment is unchanged', function (): void {
    $section = $this->contributor->compile($this->modules);
    $provider = new ConfigServiceProvider(configCacheDiscovery());

    $repository = $provider->createRepository(
        modulePaths: [$this->modulePath],
        rootConfigPath: $this->base . '/config',
        cachedDiscovery: new CachedDiscovery(['config' => $section], $this->base . '/storage/discovery.php'),
    );

    expect($repository->get('database.host'))->toBe('db.internal');
})->issue(556);

it('throws naming the variable when a variable read through Env changes after the cache is built', function (): void {
    $section = $this->contributor->compile($this->modules);
    $provider = new ConfigServiceProvider(configCacheDiscovery());
    $_ENV['MARKO_CONFIG_CACHE_HOST'] = 'db.rotated';

    expect(fn () => $provider->createRepository(
        modulePaths: [$this->modulePath],
        rootConfigPath: $this->base . '/config',
        cachedDiscovery: new CachedDiscovery(['config' => $section], $this->base . '/storage/discovery.php'),
    ))->toThrow(function (DiscoveryCacheException $exception): void {
        expect($exception->getMessage())->toContain('MARKO_CONFIG_CACHE_HOST')
            ->and($exception->getMessage())->not->toContain('MARKO_CONFIG_CACHE_PORT')
            ->and($exception->getMessage())->not->toContain('db.rotated')
            ->and($exception->getSuggestion())->toContain('discovery:cache');
    });
})->issue(556);
