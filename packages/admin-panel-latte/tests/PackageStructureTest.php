<?php

declare(strict_types=1);

describe('marko/admin-panel-latte package', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';

    test('it ships a composer.json with name marko/admin-panel-latte', function () use ($composerPath): void {
        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['name'])->toBe('marko/admin-panel-latte');
    });

    test('it requires marko/admin-panel at self.version', function () use ($composerPath): void {
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/admin-panel')
            ->and($composer['require']['marko/admin-panel'])->toBe('self.version');
    });

    test('it requires marko/view-latte at self.version', function () use ($composerPath): void {
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->toHaveKey('marko/view-latte')
            ->and($composer['require']['marko/view-latte'])->toBe('self.version');
    });

    test('it does not declare a Composer conflict block', function () use ($composerPath): void {
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toHaveKey('conflict');
    });

    test('it declares extra.marko.templates_for as marko/admin-panel', function () use ($composerPath): void {
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['templates_for'])->toBe('marko/admin-panel');
    });

    test('it marks the package as a Marko module via extra.marko.module', function () use ($composerPath): void {
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue();
    });

    test('it has a tests directory ready for the LayoutTemplateTest move', function (): void {
        $testsDir = dirname(__DIR__) . '/tests';

        expect(is_dir($testsDir))->toBeTrue();
    });

    test('it has a resources/views/ directory ready for template files', function (): void {
        $viewsDir = dirname(__DIR__) . '/resources/views';

        expect(is_dir($viewsDir))->toBeTrue();
    });
});
