<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests;

describe('Authorization Package Composer Dependencies', function (): void {
    it('creates valid composer.json with correct dependencies', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/authorization')
            ->and($composer['require'])->toHaveKey('php')
            ->and($composer['require']['php'])->toBe('^8.5')
            ->and($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require']['marko/core'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/authentication')
            ->and($composer['require']['marko/authentication'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/routing')
            ->and($composer['require']['marko/routing'])->toBe('self.version');
    });

    it('has no hardcoded version in composer.json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('version');
    });

    it('has correct autoload namespace', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Authorization\\')
            ->and($composer['autoload']['psr-4']['Marko\\Authorization\\'])->toBe('src/');
    });

    it('has marko module type', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['type'])->toBe('marko-module')
            ->and($composer['extra']['marko']['module'])->toBeTrue();
    });

    it('has no path repositories (uses self.version for Packagist publishing)', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('repositories');
    });
});
