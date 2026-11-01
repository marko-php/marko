<?php

declare(strict_types=1);

$composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

it('has a composer.json named marko/log-stream of type marko-module', function () use ($composer): void {
    expect($composer['name'])->toBe('marko/log-stream')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer)->not->toHaveKey('version')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('requires marko/log and marko/config with self.version', function () use ($composer): void {
    expect($composer['require']['marko/log'])->toBe('self.version')
        ->and($composer['require']['marko/config'])->toBe('self.version')
        ->and($composer['require']['php'])->toBe('^8.5');
});

it('autoloads the Marko\Log\Stream namespace from src/', function () use ($composer): void {
    expect($composer['autoload']['psr-4'])->toBe(['Marko\\Log\\Stream\\' => 'src/'])
        ->and($composer['autoload-dev']['psr-4'])->toBe(['Marko\\Log\\Stream\\Tests\\' => 'tests/']);
});
