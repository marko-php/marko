<?php

declare(strict_types=1);

use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;

// Helper function for recursive directory cleanup
function cleanupDirectory(string $dir): void
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
            cleanupDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Helper to create a test module with composer.json and optional module.php
function createTestModule(
    string $path,
    string $name,
    string $version = '1.0.0',
    array $require = [],
    ?array $modulePhp = null,
): void {
    mkdir($path, 0755, true);

    // Create composer.json (required)
    $composerData = [
        'name' => $name,
        'version' => $version,
    ];
    if (!empty($require)) {
        $composerData['require'] = $require;
    }
    file_put_contents($path . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    // Create module.php (optional)
    if ($modulePhp !== null) {
        $modulePhpContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($modulePhp, true) . ";\n";
        file_put_contents($path . '/module.php', $modulePhpContent);
    }
}

it('parses a module with composer.json into ModuleManifest object', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '1.0.0');

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest)->toBeInstanceOf(ModuleManifest::class);

    cleanupDirectory($tempDir);
});

it('extracts module name from composer.json', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog');

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->name)->toBe('acme/blog');

    cleanupDirectory($tempDir);
});

it('extracts module version from composer.json', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '2.5.3');

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->version)->toBe('2.5.3');

    cleanupDirectory($tempDir);
});

it('works without module.php file using sensible defaults', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();

    // Create module with ONLY composer.json, no module.php
    createTestModule($tempDir, 'acme/minimal', '1.0.0');

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    // All defaults should be applied
    expect($manifest->name)->toBe('acme/minimal')
        ->and($manifest->version)->toBe('1.0.0')
        ->and($manifest->enabled)->toBeTrue()
        ->and($manifest->after)->toBe([])
        ->and($manifest->before)->toBe([])
        ->and($manifest->bindings)->toBe([]);

    cleanupDirectory($tempDir);
});

it('extracts enabled state from module.php defaulting to true', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();

    // Test default (no module.php = enabled true)
    createTestModule($tempDir, 'acme/blog');

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->enabled)->toBeTrue();

    cleanupDirectory($tempDir);

    // Test explicit false
    $tempDir2 = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir2, 'acme/disabled', '1.0.0', [], ['enabled' => false]);

    $manifest = $parser->parse($tempDir2);

    expect($manifest->enabled)->toBeFalse();

    cleanupDirectory($tempDir2);
});

it('extracts require dependencies from composer.json', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '1.0.0', [
        'php' => '^8.5',
        'marko/core' => '^1.0',
        'marko/database' => '^1.0',
    ]);

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    // Should filter out php requirement
    expect($manifest->require)
        ->toBeArray()
        ->toHaveKey('marko/core')
        ->toHaveKey('marko/database')
        ->not->toHaveKey('php');

    cleanupDirectory($tempDir);
});

it('extracts sequence hints (after/before) from module.php', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '1.0.0', [], [
        'sequence' => [
            'after' => ['marko/core', 'marko/database'],
            'before' => ['acme/admin'],
        ],
    ]);

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->after)
        ->toBeArray()
        ->toContain('marko/core')
        ->toContain('marko/database')
        ->and($manifest->before)
        ->toBeArray()
        ->toContain('acme/admin');

    cleanupDirectory($tempDir);
});

it('extracts bindings from module.php', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '1.0.0', [], [
        'bindings' => [
            'Acme\Blog\Contracts\PostRepositoryInterface' => 'Acme\Blog\Repositories\PostRepository',
        ],
    ]);

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->bindings)
        ->toBeArray()
        ->toHaveKey('Acme\Blog\Contracts\PostRepositoryInterface')
        ->and($manifest->bindings['Acme\Blog\Contracts\PostRepositoryInterface'])
        ->toBe('Acme\Blog\Repositories\PostRepository');

    cleanupDirectory($tempDir);
});

it('throws ModuleException when composer.json is missing', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    // No composer.json created

    $parser = new ManifestParser();

    expect(fn () => $parser->parse($tempDir))->toThrow(ModuleException::class);

    cleanupDirectory($tempDir);
});

it('throws ModuleException when composer.json is invalid JSON', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/composer.json', '{ invalid json }');

    $parser = new ManifestParser();

    expect(fn () => $parser->parse($tempDir))->toThrow(ModuleException::class);

    cleanupDirectory($tempDir);
});

it('throws ModuleException when composer.json missing required name field', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/composer.json', json_encode(['version' => '1.0.0']));

    $parser = new ManifestParser();

    $exception = null;

    try {
        $parser->parse($tempDir);
    } catch (ModuleException $e) {
        $exception = $e;
    }

    cleanupDirectory($tempDir);

    expect($exception)->not->toBeNull()
        ->and($exception)->toBeInstanceOf(ModuleException::class)
        ->and($exception->getMessage())->toContain('name');
});

it('throws ModuleException when module.php has syntax errors', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    file_put_contents($tempDir . '/composer.json', json_encode(['name' => 'acme/blog']));
    file_put_contents(
        $tempDir . '/module.php',
        "<?php\nreturn [\n    'enabled' => true\n    // missing comma - syntax error\n    'bindings' => []\n];",
    );

    $parser = new ManifestParser();

    expect(fn () => $parser->parse($tempDir))->toThrow(ModuleException::class);

    cleanupDirectory($tempDir);
});

it('discovers modules in vendor directory two levels deep', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';

    // Create vendor/marko/core
    createTestModule($vendorDir . '/marko/core', 'marko/core');

    // Create vendor/acme/blog
    createTestModule($vendorDir . '/acme/blog', 'acme/blog');

    // Create a 3-levels-deep module that should NOT be discovered
    createTestModule($vendorDir . '/deep/nested/module', 'deep/nested');

    $discovery = new ModuleDiscovery(new ManifestParser());
    $modules = $discovery->discoverInVendor($vendorDir);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)
        ->toContain('marko/core')
        ->toContain('acme/blog')
        ->not->toContain('deep/nested');

    cleanupDirectory($baseDir);
});

it('discovers modules in modules directory recursively', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $modulesDir = $baseDir . '/modules';

    // Create modules/custom-module
    createTestModule($modulesDir . '/custom-module', 'custom/module');

    // Create modules/company/internal-auth (nested)
    createTestModule($modulesDir . '/company/internal-auth', 'company/internal-auth');

    // Create modules/deep/nested/module (deeply nested)
    createTestModule($modulesDir . '/deep/nested/module', 'deep/nested/module');

    $discovery = new ModuleDiscovery(new ManifestParser());
    $modules = $discovery->discoverInModules($modulesDir);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)
        ->toContain('custom/module')
        ->toContain('company/internal-auth')
        ->toContain('deep/nested/module');

    cleanupDirectory($baseDir);
});

it('discovers modules in app directory one level deep', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $appDir = $baseDir . '/app';

    // Create app/blog
    createTestModule($appDir . '/blog', 'app/blog');

    // Create app/admin
    createTestModule($appDir . '/admin', 'app/admin');

    // Create a nested module that should NOT be discovered (too deep)
    createTestModule($appDir . '/nested/module', 'app/nested/module');

    $discovery = new ModuleDiscovery(new ManifestParser());
    $modules = $discovery->discoverInApp($appDir);

    $names = array_map(fn (ModuleManifest $m) => $m->name, $modules);

    expect($names)
        ->toContain('app/blog')
        ->toContain('app/admin')
        ->not->toContain('app/nested/module');

    cleanupDirectory($baseDir);
});

it('skips directories without composer.json file', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $appDir = $baseDir . '/app';

    // Create vendor/marko/core WITH composer.json
    createTestModule($vendorDir . '/marko/core', 'marko/core');

    // Create vendor/other/package WITHOUT composer.json (not a module)
    mkdir($vendorDir . '/other/package/src', 0755, true);
    file_put_contents($vendorDir . '/other/package/src/SomeClass.php', '<?php class SomeClass {}');

    // Create app/blog WITH composer.json
    createTestModule($appDir . '/blog', 'app/blog');

    // Create app/lib WITHOUT composer.json (not a module)
    mkdir($appDir . '/lib', 0755, true);
    file_put_contents($appDir . '/lib/helpers.php', '<?php // helpers');

    $discovery = new ModuleDiscovery(new ManifestParser());

    $vendorModules = $discovery->discoverInVendor($vendorDir);
    $appModules = $discovery->discoverInApp($appDir);

    expect($vendorModules)->toHaveCount(1)
        ->and($vendorModules[0]->name)->toBe('marko/core')
        ->and($appModules)->toHaveCount(1)
        ->and($appModules[0]->name)->toBe('app/blog');

    cleanupDirectory($baseDir);
});

it('returns discovered modules with their source directory (vendor/modules/app)', function (): void {
    $baseDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    $vendorDir = $baseDir . '/vendor';
    $modulesDir = $baseDir . '/modules';
    $appDir = $baseDir . '/app';

    // Create vendor module
    createTestModule($vendorDir . '/marko/core', 'marko/core');

    // Create modules module
    createTestModule($modulesDir . '/custom/checkout', 'custom/checkout');

    // Create app module
    createTestModule($appDir . '/blog', 'app/blog');

    $discovery = new ModuleDiscovery(new ManifestParser());

    $vendorModules = $discovery->discoverInVendor($vendorDir);
    $customModules = $discovery->discoverInModules($modulesDir);
    $appModules = $discovery->discoverInApp($appDir);

    // Check vendor module has correct source and path
    expect($vendorModules[0]->source)->toBe('vendor')
        ->and($vendorModules[0]->path)->toBe($vendorDir . '/marko/core');

    // Check modules module has correct source and path
    expect($customModules[0]->source)->toBe('modules')
        ->and($customModules[0]->path)->toBe($modulesDir . '/custom/checkout');

    // Check app module has correct source and path
    expect($appModules[0]->source)->toBe('app')
        ->and($appModules[0]->path)->toBe($appDir . '/blog');

    cleanupDirectory($baseDir);
});

it('filters out php and extension requirements from dependencies', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    createTestModule($tempDir, 'acme/blog', '1.0.0', [
        'php' => '^8.5',
        'ext-json' => '*',
        'ext-pdo' => '*',
        'lib-pcre' => '*',
        'marko/core' => '^1.0',
        'psr/container' => '^2.0',
    ]);

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->require)
        ->toHaveKey('marko/core')
        ->toHaveKey('psr/container')
        ->not->toHaveKey('php')
        ->not->toHaveKey('ext-json')
        ->not->toHaveKey('ext-pdo')
        ->not->toHaveKey('lib-pcre');

    cleanupDirectory($tempDir);
});

it('extracts psr-4 autoload configuration from composer.json into ModuleManifest', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);

    // Create composer.json with autoload.psr-4 configuration
    $composerData = [
        'name' => 'acme/blog',
        'version' => '1.0.0',
        'autoload' => [
            'psr-4' => [
                'Acme\\Blog\\' => 'src/',
            ],
        ],
    ];
    file_put_contents($tempDir . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->autoload)
        ->toBeArray()
        ->toHaveKey('Acme\\Blog\\')
        ->and($manifest->autoload['Acme\\Blog\\'])->toBe('src/');

    cleanupDirectory($tempDir);
});

it('stores autoload as empty array when composer.json has no autoload section', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-test-' . uniqid();
    mkdir($tempDir, 0755, true);

    // Create composer.json WITHOUT autoload section
    $composerData = [
        'name' => 'acme/simple',
        'version' => '1.0.0',
    ];
    file_put_contents($tempDir . '/composer.json', json_encode($composerData, JSON_PRETTY_PRINT));

    $parser = new ManifestParser();
    $manifest = $parser->parse($tempDir);

    expect($manifest->autoload)
        ->toBeArray()
        ->toBeEmpty();

    cleanupDirectory($tempDir);
});
