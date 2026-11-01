<?php

declare(strict_types=1);

use Marko\Core\Module\CachedModule;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;

/**
 * Create a temp module directory with a composer.json and an optional module.php body.
 */
function cachedManifestModule(
    ?string $modulePhp = null,
): string {
    $dir = sys_get_temp_dir() . '/marko-cached-manifest-' . bin2hex(random_bytes(6));
    mkdir($dir, 0755, true);
    file_put_contents($dir . '/composer.json', json_encode([
        'name' => 'acme/blog',
        'version' => '2.1.0',
        'require' => ['php' => '^8.5', 'marko/core' => '*'],
        'autoload' => ['psr-4' => ['Acme\\Blog\\' => 'src/']],
        'extra' => ['marko' => ['module' => true]],
    ]));

    if ($modulePhp !== null) {
        file_put_contents($dir . '/module.php', $modulePhp);
    }

    return $dir;
}

function cachedManifestCleanup(
    string $dir,
): void {
    array_map('unlink', glob($dir . '/*') ?: []);
    rmdir($dir);
}

describe('ManifestParser cached modules', function (): void {
    it('parses the discovery contributor list from module.php', function (): void {
        $dir = cachedManifestModule("<?php return ['discovery' => ['Acme\\\\Blog\\\\BlogContributor']];");

        $manifest = (new ManifestParser())->parse($dir);
        cachedManifestCleanup($dir);

        expect($manifest->discovery)->toBe(['Acme\\Blog\\BlogContributor']);
    });

    it('defaults the discovery contributor list to empty', function (): void {
        $dir = cachedManifestModule();

        $manifest = (new ManifestParser())->parse($dir);
        cachedManifestCleanup($dir);

        expect($manifest->discovery)->toBe([]);
    });

    it('builds a manifest from a cached module without reading composer.json', function (): void {
        $dir = cachedManifestModule("<?php return ['sequence' => ['after' => ['acme/core']]];");
        unlink($dir . '/composer.json');

        $manifest = (new ManifestParser())->parseCached(new CachedModule(
            name: 'acme/blog',
            version: '2.1.0',
            path: $dir,
            source: 'app',
            require: ['marko/core' => '*'],
            autoload: ['Acme\\Blog\\' => 'src/'],
            extra: ['marko' => ['module' => true]],
        ));
        cachedManifestCleanup($dir);

        expect($manifest->name)->toBe('acme/blog')
            ->and($manifest->version)->toBe('2.1.0')
            ->and($manifest->path)->toBe($dir)
            ->and($manifest->source)->toBe('app')
            ->and($manifest->require)->toBe(['marko/core' => '*'])
            ->and($manifest->autoload)->toBe(['Acme\\Blog\\' => 'src/'])
            ->and($manifest->extra)->toBe(['marko' => ['module' => true]])
            ->and($manifest->after)->toBe(['acme/core']);
    });

    it('keeps module.php closures live when building a manifest from a cached module', function (): void {
        $dir = cachedManifestModule(
            "<?php return ['boot' => fn (): string => 'booted', 'shutdown' => fn (): string => 'shut down', 'bindings' => ['A' => fn (): string => 'b']];",
        );

        $manifest = (new ManifestParser())->parseCached(new CachedModule(
            name: 'acme/blog',
            version: '2.1.0',
            path: $dir,
            source: 'vendor',
        ));
        cachedManifestCleanup($dir);

        expect($manifest->boot)->toBeInstanceOf(Closure::class)
            ->and(($manifest->boot)())->toBe('booted')
            ->and($manifest->shutdown)->toBeInstanceOf(Closure::class)
            ->and(($manifest->shutdown)())->toBe('shut down')
            ->and($manifest->bindings['A'])->toBeInstanceOf(Closure::class);
    });

    it('keeps the discovery list when ModuleDiscovery sets path and source', function (): void {
        $app = sys_get_temp_dir() . '/marko-cached-manifest-app-' . bin2hex(random_bytes(6));
        mkdir($app);
        $dir = cachedManifestModule("<?php return ['discovery' => ['Acme\\\\Blog\\\\BlogContributor']];");
        rename($dir, $app . '/blog');

        $modules = new ModuleDiscovery(new ManifestParser())->discoverInApp($app);
        cachedManifestCleanup($app . '/blog');
        rmdir($app);

        expect($modules)->toHaveCount(1)
            ->and($modules[0]->discovery)->toBe(['Acme\\Blog\\BlogContributor'])
            ->and($modules[0]->source)->toBe('app');
    });
});
