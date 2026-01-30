<?php

declare(strict_types=1);

describe('Package Scaffolding', function (): void {
    it('queue composer.json exists with correct name', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/queue');
    });

    it('queue composer.json has proper autoload configuration', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Queue\\')
            ->and($composer['autoload']['psr-4']['Marko\\Queue\\'])->toBe('src/')
            ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Queue\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\Queue\\Tests\\'])->toBe('tests/');
    });

    it('queue-sync composer.json exists with correct name', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/queue-sync/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/queue-sync');
    });

    it('queue-sync composer.json depends on marko/queue', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/queue-sync/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/queue');
    });

    it('queue-database composer.json exists with correct name', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/queue-database/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/queue-database');
    });

    it('queue-database composer.json depends on marko/queue and marko/database', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/queue-database/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/queue')
            ->and($composer['require'])->toHaveKey('marko/database');
    });
});
