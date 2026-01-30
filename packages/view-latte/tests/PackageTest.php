<?php

declare(strict_types=1);

describe('marko/view-latte package', function (): void {
    test('composer.json exists with correct namespace', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['name'])->toBe('marko/view-latte')
            ->and($composer['autoload']['psr-4']['Marko\\View\\Latte\\'])->toBe('src/');
    });

    test('composer.json requires marko/view', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/view');
    });

    test('composer.json requires latte/latte', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('latte/latte')
            ->and($composer['require']['latte/latte'])->toBe('^3.0');
    });
});
