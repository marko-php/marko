<?php

declare(strict_types=1);

describe('Package Scaffolding', function (): void {
    it('mail composer.json exists with correct name', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/mail');
    });

    it('mail composer.json has proper autoload configuration', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Mail\\')
            ->and($composer['autoload']['psr-4']['Marko\\Mail\\'])->toBe('src/')
            ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Mail\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\Mail\\Tests\\'])->toBe('tests/');
    });

    it('mail-smtp composer.json exists with correct name', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/mail-smtp/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/mail-smtp');
    });

    it('mail-smtp composer.json depends on marko/mail', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/mail-smtp/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/mail');
    });

    it('mail-smtp composer.json has proper autoload configuration', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/mail-smtp/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\Mail\\Smtp\\')
            ->and($composer['autoload']['psr-4']['Marko\\Mail\\Smtp\\'])->toBe('src/')
            ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Mail\\Smtp\\Tests\\')
            ->and($composer['autoload-dev']['psr-4']['Marko\\Mail\\Smtp\\Tests\\'])->toBe('tests/');
    });
});
