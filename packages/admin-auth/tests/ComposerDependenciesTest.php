<?php

declare(strict_types=1);

describe('AdminAuth Package Composer Dependencies', function (): void {
    it('has valid composer.json with admin, auth, and database dependencies', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/admin-auth')
            ->and($composer['require'])->toHaveKey('marko/admin')
            ->and($composer['require']['marko/admin'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/authentication')
            ->and($composer['require']['marko/authentication'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/database')
            ->and($composer['require']['marko/database'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require']['marko/core'])->toBe('self.version')
            ->and($composer['require'])->toHaveKey('marko/config')
            ->and($composer['require']['marko/config'])->toBe('self.version');
    });

    it('has no path repositories (uses self.version for Packagist publishing)', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('repositories');
    });

    it('does not depend on any specific database driver', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->not->toHaveKey('marko/database-mysql')
            ->and($composer['require'])->not->toHaveKey('marko/database-pgsql');
    });

    it('has correct autoload namespace', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\AdminAuth\\')
            ->and($composer['autoload']['psr-4']['Marko\\AdminAuth\\'])->toBe('src/');
    });

    it('has marko module type', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['type'])->toBe('marko-module')
            ->and($composer['extra']['marko']['module'])->toBeTrue();
    });
});
