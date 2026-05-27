<?php

declare(strict_types=1);

it('composer.json exists with correct namespace', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue()
        ->and(json_decode(file_get_contents($composerPath), true))->toBeArray()
        ->and(json_decode(file_get_contents($composerPath), true)['name'])->toBe('marko/view')
        ->and(json_decode(file_get_contents($composerPath), true)['autoload']['psr-4']['Marko\\View\\'])->toBe('src/');
});

it('composer.json has marko/core dependency', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('require')
        ->and($composer['require'])->toHaveKey('marko/core');
});

it('marko/view test suite contains no imports of Marko\\View\\Latte namespace', function (): void {
    $testsDir = __DIR__;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testsDir));
    $phpFiles = new RegexIterator($iterator, '/\.php$/');

    // Build the pattern indirectly so this file does not match itself
    $forbiddenNamespace = implode('\\', ['Marko', 'View', 'Latte']);

    $violations = [];
    foreach ($phpFiles as $file) {
        $contents = file_get_contents($file->getPathname());
        if (str_contains($contents, $forbiddenNamespace)) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty('Found ' . $forbiddenNamespace . ' imports in: ' . implode(', ', $violations));
});
