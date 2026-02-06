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
            ->and($composer['require']['marko/admin'])->toBe('@dev')
            ->and($composer['require'])->toHaveKey('marko/auth')
            ->and($composer['require']['marko/auth'])->toBe('@dev')
            ->and($composer['require'])->toHaveKey('marko/database')
            ->and($composer['require']['marko/database'])->toBe('@dev')
            ->and($composer['require'])->toHaveKey('marko/core')
            ->and($composer['require']['marko/core'])->toBe('@dev')
            ->and($composer['require'])->toHaveKey('marko/config')
            ->and($composer['require']['marko/config'])->toBe('@dev');
    });

    it('adds path repositories for dependencies in development', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['repositories'])->toBeArray();

        $expectedRepos = ['../core', '../admin', '../auth', '../database', '../config'];
        $foundRepos = [];

        foreach ($composer['repositories'] as $repo) {
            if ($repo['type'] === 'path') {
                $foundRepos[] = $repo['url'];
            }
        }

        foreach ($expectedRepos as $expectedUrl) {
            expect($foundRepos)->toContain($expectedUrl);
        }
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
