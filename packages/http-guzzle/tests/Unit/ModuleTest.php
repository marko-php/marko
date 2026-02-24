<?php

declare(strict_types=1);

use Marko\Http\Contracts\HttpClientInterface;
use Marko\Http\Guzzle\GuzzleHttpClient;

describe('module.php', function (): void {
    it('module.php exists with correct structure', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $module = require $modulePath;

        expect($module)->toBeArray()
            ->and($module)->toHaveKey('bindings')
            ->and($module['bindings'])->toBeArray();
    });

    it('binds HttpClientInterface to GuzzleHttpClient', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $module = require $modulePath;

        expect($module['bindings'])->toHaveKey(HttpClientInterface::class)
            ->and($module['bindings'][HttpClientInterface::class])->toBe(GuzzleHttpClient::class);
    });

    it('has marko module flag in composer.json', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['extra']['marko']['module'])->toBeTrue();
    });
});
