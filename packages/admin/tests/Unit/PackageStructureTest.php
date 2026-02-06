<?php

declare(strict_types=1);

it('has valid composer.json with correct dependencies and autoload', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';

    expect(file_exists($composerPath))->toBeTrue('composer.json should exist');

    $content = file_get_contents($composerPath);
    $composer = json_decode($content, true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'composer.json should be valid JSON')
        ->and($composer['name'])->toBe('marko/admin')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/core'])->toBe('@dev')
        ->and($composer['require']['marko/routing'])->toBe('@dev')
        ->and($composer['require-dev']['pestphp/pest'])->toBe('^4.0')
        ->and($composer['autoload']['psr-4'])->toHaveKey('Marko\\Admin\\')
        ->and($composer['autoload']['psr-4']['Marko\\Admin\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4'])->toHaveKey('Marko\\Admin\\Tests\\')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Admin\\Tests\\'])->toBe('tests/')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});
