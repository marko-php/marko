<?php

declare(strict_types=1);

describe('marko/view-twig package', function (): void {
    test('it ships a composer.json with name marko/view-twig', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['name'])->toBe('marko/view-twig');
    });

    test('it requires php ^8.5', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('php')
            ->and($composer['require']['php'])->toBe('^8.5');
    });

    test('it requires marko/view at self.version', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/view')
            ->and($composer['require']['marko/view'])->toBe('self.version');
    });

    test('it requires twig/twig ^3.0', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('twig/twig')
            ->and($composer['require']['twig/twig'])->toBe('^3.0');
    });

    test('it autoloads Marko\\View\\Twig namespace from src/', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\View\\Twig\\')
            ->and($composer['autoload']['psr-4']['Marko\\View\\Twig\\'])->toBe('src/');
    });

    test('it autoloads Marko\\View\\Twig\\Tests namespace from tests/', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\View\\Twig\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\View\\Twig\\Tests\\'])->toBe('tests/');
    });

    test('it marks the package as a Marko module via extra.marko.module', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue();
    });
});
