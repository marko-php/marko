<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests;

describe('Roadrunner Package Composer Configuration', function (): void {
    it('exposes a composer package named marko slash roadrunner', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/roadrunner');
    });

    it('declares no version key in composer json', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('version');
    });

    it('declares marko interdependencies using self dot version', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require']['marko/core'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/routing')
            ->and($composer['require']['marko/routing'])->toBe('self.version')
            ->and($composer['require-dev'])->toHaveKey('marko/sse')
            ->and($composer['require-dev']['marko/sse'])->toBe('self.version');
    });

    it('registers the package as a marko module in composer extra', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue();
    });

    it('autoloads the package namespace from the src directory', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Roadrunner\\')
            ->and($composer['autoload']['psr-4']['Marko\\Roadrunner\\'])->toBe('src/');
    });
});
