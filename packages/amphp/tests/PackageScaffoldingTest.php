<?php

declare(strict_types=1);

describe('Package Scaffolding', function (): void {
    it('has valid module.php for marko/amphp with empty bindings', function (): void {
        $modulePath = dirname(__DIR__) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toBeEmpty();
    });

    it('has valid composer.json with name marko/amphp and amphp/amp dependency', function (): void {
        $composerPath = dirname(__DIR__) . '/composer.json';

        expect(file_exists($composerPath))->toBeTrue();

        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->not->toBeNull()
            ->and($composer['name'])->toBe('marko/amphp')
            ->and($composer['require'])->toHaveKey('amphp/amp');
    });
});
