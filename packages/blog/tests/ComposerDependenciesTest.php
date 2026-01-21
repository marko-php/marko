<?php

declare(strict_types=1);

describe('Blog Package Composer Dependencies', function (): void {
    it('requires marko/database as a dependency', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['require'])->toHaveKey('marko/database')
            ->and($composer['require']['marko/database'])->toBe('@dev');
    });

    it('adds path repository for database package in development', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['repositories'])->toBeArray();

        $databaseRepoFound = false;
        foreach ($composer['repositories'] as $repo) {
            if ($repo['type'] === 'path' && $repo['url'] === '../database') {
                $databaseRepoFound = true;
                break;
            }
        }

        expect($databaseRepoFound)->toBeTrue();
    });

    it('does not depend on any specific database driver', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->not->toHaveKey('marko/database-mysql')
            ->and($composer['require'])->not->toHaveKey('marko/database-pgsql');
    });
});
