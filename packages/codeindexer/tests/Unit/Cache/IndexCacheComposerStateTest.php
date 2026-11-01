<?php

declare(strict_types=1);

use Marko\CodeIndexer\Attributes\AttributeParser;
use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Config\ConfigScanner;
use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\Translations\TranslationScanner;
use Marko\CodeIndexer\ValueObject\ConfigKeyEntry;
use Marko\CodeIndexer\Views\TemplateScanner;
use Marko\Core\Path\ProjectPaths;

// ── helpers ─────────────────────────────────────────────────────────────────

$composerStateRoots = [];

afterEach(function () use (&$composerStateRoots): void {
    foreach ($composerStateRoots as $dir) {
        composerStateRemoveDir($dir);
    }
    $composerStateRoots = [];
});

function composerStateRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }

    rmdir($dir);
}

function composerStateProject(): string
{
    $root = sys_get_temp_dir() . '/codeindexer-composer-state-' . uniqid();
    mkdir($root . '/vendor/composer', 0755, true);
    file_put_contents($root . '/vendor/composer/installed.json', '{"packages":[]}');

    return $root;
}

function composerStateVendorPackage(
    string $root,
    string $name,
    string $configName,
): string {
    $path = $root . '/vendor/' . $name;
    mkdir($path . '/config', 0755, true);
    file_put_contents($path . '/composer.json', json_encode([
        'name' => $name,
        'type' => 'marko-module',
        'extra' => ['marko' => ['module' => true]],
    ]));
    file_put_contents(
        $path . '/config/' . $configName . '.php',
        "<?php\n\ndeclare(strict_types=1);\n\nreturn ['endpoint' => 'x'];\n",
    );

    return $path;
}

function composerStateCache(string $root): IndexCache
{
    $paths = new ProjectPaths($root);

    return new IndexCache(
        paths: $paths,
        moduleWalker: new ModuleWalker($paths),
        attributeParser: new AttributeParser(),
        configScanner: new ConfigScanner(),
        templateScanner: new TemplateScanner(),
        translationScanner: new TranslationScanner(),
    );
}

/** @return list<string> */
function composerStateKeys(IndexCache $cache): array
{
    return array_map(fn (ConfigKeyEntry $e): string => $e->key, $cache->getConfigKeys());
}

function composerStateTouchAfterCache(
    string $root,
    string $file,
): void {
    touch($file, filemtime($root . '/.marko/index.cache') + 10);
}

// ── tests ─────────────────────────────────────────────────────────────────────

it(
    'marks the index stale and picks up a vendor package added by composer',
    function () use (&$composerStateRoots): void {
        $root = composerStateProject();
        $composerStateRoots[] = $root;
    
        $cache = composerStateCache($root);
        $cache->build();
    
        expect($cache->isStale())->toBeFalse()
            ->and(composerStateKeys($cache))->not->toContain('new.endpoint');
    
        composerStateVendorPackage($root, 'acme/new', 'new');
        composerStateTouchAfterCache($root, $root . '/vendor/composer/installed.json');
    
        expect($cache->isStale())->toBeTrue()
            ->and(composerStateKeys($cache))->toContain('new.endpoint')
            ->and($cache->isStale())->toBeFalse();
    }
);

it(
    'marks the index stale and drops the keys of a vendor package removed by composer',
    function () use (&$composerStateRoots): void {
        $root = composerStateProject();
        $composerStateRoots[] = $root;
        $packagePath = composerStateVendorPackage($root, 'acme/old', 'old');
    
        $cache = composerStateCache($root);
        $cache->build();
    
        expect(composerStateKeys($cache))->toContain('old.endpoint')
            ->and($cache->isStale())->toBeFalse();
    
        composerStateRemoveDir($packagePath);
        composerStateTouchAfterCache($root, $root . '/vendor/composer/installed.json');
    
        expect($cache->isStale())->toBeTrue()
            ->and(composerStateKeys($cache))->not->toContain('old.endpoint');
    }
);

it(
    'falls back to composer.lock when vendor/composer/installed.json is absent',
    function () use (&$composerStateRoots): void {
        $root = composerStateProject();
        $composerStateRoots[] = $root;
        unlink($root . '/vendor/composer/installed.json');
        file_put_contents($root . '/composer.lock', '{"packages":[]}');
    
        $cache = composerStateCache($root);
        $cache->build();
    
        expect($cache->isStale())->toBeFalse();
    
        composerStateTouchAfterCache($root, $root . '/composer.lock');
    
        expect($cache->isStale())->toBeTrue();
    }
);

it('does not reload a fresh cache from disk after a composer change', function () use (&$composerStateRoots): void {
    $root = composerStateProject();
    $composerStateRoots[] = $root;

    composerStateCache($root)->build();

    composerStateVendorPackage($root, 'acme/new', 'new');
    composerStateTouchAfterCache($root, $root . '/vendor/composer/installed.json');

    $fresh = composerStateCache($root);

    expect($fresh->load())->toBeFalse()
        ->and(composerStateKeys($fresh))->toContain('new.endpoint');
});

it('indexes project-root config files and tracks them for staleness', function () use (&$composerStateRoots): void {
    $root = composerStateProject();
    $composerStateRoots[] = $root;
    mkdir($root . '/config', 0755, true);
    file_put_contents(
        $root . '/config/app_only.php',
        "<?php\n\ndeclare(strict_types=1);\n\nreturn ['foo' => 'bar'];\n",
    );

    $cache = composerStateCache($root);
    $keys = composerStateKeys($cache);

    $entry = array_find(
        $cache->getConfigKeys(),
        fn (ConfigKeyEntry $e): bool => $e->key === 'app_only.foo',
    );

    expect($keys)->toContain('app_only.foo')
        ->and($entry?->file)->toBe($root . '/config/app_only.php')
        ->and($cache->isStale())->toBeFalse();

    // Editing an existing root config file makes the index stale.
    file_put_contents(
        $root . '/config/app_only.php',
        "<?php\n\ndeclare(strict_types=1);\n\nreturn ['foo' => 'bar', 'baz' => 1];\n",
    );
    composerStateTouchAfterCache($root, $root . '/config/app_only.php');

    expect($cache->isStale())->toBeTrue()
        ->and(composerStateKeys($cache))->toContain('app_only.baz');

    // Bring the rebuilt cache back ahead of the future-dated file.
    touch($root . '/.marko/index.cache', filemtime($root . '/config/app_only.php') + 10);

    expect($cache->isStale())->toBeFalse();

    // Adding a new root config file makes the index stale, even with an older mtime.
    file_put_contents(
        $root . '/config/extra.php',
        "<?php\n\ndeclare(strict_types=1);\n\nreturn ['enabled' => true];\n",
    );
    touch($root . '/config/extra.php', filemtime($root . '/.marko/index.cache') - 100);

    expect($cache->isStale())->toBeTrue()
        ->and(composerStateKeys($cache))->toContain('extra.enabled');
});
