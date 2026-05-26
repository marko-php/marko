<?php

declare(strict_types=1);

it('declares a Composer conflict with marko/view-twig in composer.json', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer)->toHaveKey('conflict')
        ->and($composer['conflict'])->toHaveKey('marko/view-twig')
        ->and($composer['conflict']['marko/view-twig'])->toBe('*');
});
