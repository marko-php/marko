<?php

declare(strict_types=1);

describe('module.php', function (): void {
    it('has marko module flag in composer.json', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue();
    });

    it('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray()
            ->and($module['bindings'])->toBeEmpty();
    });
});
