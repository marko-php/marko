<?php

declare(strict_types=1);

describe('Package Setup', function () {
    $composerJsonPath = dirname(__DIR__, 2) . '/composer.json';
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);

    it('has valid composer.json with correct name', function () use ($composerJson) {
        expect($composerJson['name'])->toBe('marko/errors-advanced');
    });

    it('has required PHP version 8.5', function () use ($composerJson) {
        expect($composerJson['require']['php'])->toMatch('/\^?>=?8\.5/');
    });

    it('depends on marko/errors', function () use ($composerJson) {
        expect($composerJson['require'])->toHaveKey('marko/errors');
    });

    it('has PSR-4 autoload configuration', function () use ($composerJson) {
        expect($composerJson['autoload']['psr-4'])->toHaveKey('Marko\\ErrorsAdvanced\\')
            ->and($composerJson['autoload']['psr-4']['Marko\\ErrorsAdvanced\\'])->toBe('src/');
    });

    it('has src directory structure', function () {
        $srcPath = dirname(__DIR__, 2) . '/src';
        expect(is_dir($srcPath))->toBeTrue();
    });

    it('has tests directory', function () {
        $testsPath = dirname(__DIR__, 2) . '/tests';
        expect(is_dir($testsPath))->toBeTrue();
    });
});
