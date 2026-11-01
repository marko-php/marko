<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;

it('creates valid package scaffolding with composer.json, module.php, and config', function () {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(json_decode(file_get_contents($packageRoot . '/composer.json'), true)['name'])->toBe('marko/health')
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/health.php'))->toBeTrue();

    $config = require $packageRoot . '/config/health.php';

    // The route path is fixed by the #[Get('/health')] attribute, so there is no
    // 'path' key; 'secret' is the only setting.
    expect(array_keys($config))->toBe(['secret']);

    $module = require $packageRoot . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});

it('declares every Marko package its source imports, optional ones in suggest and require-dev', function (): void {
    $packageRoot = dirname(__DIR__);
    $composer = json_decode(file_get_contents($packageRoot . '/composer.json'), true);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packageRoot . '/src'));
    $importedPackages = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all('/^use (Marko\\\\[^;\s]+);/m', file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $importedClass) {
            $importedPackages[MarkoException::inferBasePackageName($importedClass)] = true;
        }
    }

    unset($importedPackages['marko/health']);

    expect($importedPackages)->toHaveKeys(['marko/cache', 'marko/database', 'marko/filesystem']);

    foreach (array_keys($importedPackages) as $package) {
        if (isset($composer['require'][$package])) {
            continue;
        }

        expect(isset($composer['suggest'][$package]))->toBeTrue("$package is imported but not required or suggested")
            ->and(isset($composer['require-dev'][$package]))->toBeTrue("$package is suggested but not in require-dev");
    }
});
