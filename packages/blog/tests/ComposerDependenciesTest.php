<?php

declare(strict_types=1);

describe('Blog Package Composer Dependencies', function (): void {
    it('requires marko/database as a dependency', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['require'])->toHaveKey('marko/database')
            ->and($composer['require']['marko/database'])->toBe('self.version');
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

    it('suggests marko/mail-log driver', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['suggest'])->toHaveKey('marko/mail-log');
    });

    it('suggests marko/mail-smtp driver', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['suggest'])->toHaveKey('marko/mail-smtp');
    });
});
