<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\DevServer\Exceptions\DevServerException;

it('has valid composer.json with correct name and dependencies', function (): void {
    $composerPath = __DIR__ . '/../composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/dev-server')
        ->and($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/config');
});

it('has PSR-4 autoloading configured', function (): void {
    $composerPath = __DIR__ . '/../composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\DevServer\\')
        ->and($composer['autoload']['psr-4']['Marko\\DevServer\\'])->toBe('src/');
});

it('has module.php with marko module marker', function (): void {
    $modulePath = __DIR__ . '/../module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toHaveKey('bindings');
});

it('has DevServerException extending MarkoException', function (): void {
    $exception = DevServerException::processFailedToStart('php', 'php -S localhost:8000');

    expect($exception)->toBeInstanceOf(DevServerException::class)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toContain('php')
        ->and($exception->getContext())->not->toBeEmpty()
        ->and($exception->getSuggestion())->not->toBeEmpty();
});

it('has config/dev.php with default values', function (): void {
    $configPath = __DIR__ . '/../config/dev.php';

    expect(file_exists($configPath))->toBeTrue();

    $config = require $configPath;

    expect($config)->toHaveKey('port')
        ->and($config)->toHaveKey('detach')
        ->and($config)->toHaveKey('docker')
        ->and($config)->toHaveKey('frontend')
        ->and($config['port'])->toBe(8000)
        ->and($config['detach'])->toBeTrue()
        ->and($config['docker'])->toBeTrue()
        ->and($config['frontend'])->toBeTrue();
});

it('adds pubsub toggle to dev config with default true', function (): void {
    $config = require __DIR__ . '/../config/dev.php';

    expect($config)->toHaveKey('pubsub')
        ->and($config['pubsub'])->toBeTrue();
});

it('provides default config values in config/dev.php', function (): void {
    $config = require __DIR__ . '/../config/dev.php';

    expect($config['port'])->toBe(8000)
        ->and($config['detach'])->toBeTrue()
        ->and($config['docker'])->toBeTrue()
        ->and($config['frontend'])->toBeTrue();
});

it('has README.md with required sections', function (): void {
    $readmePath = __DIR__ . '/../README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# marko/dev-server')
        ->toContain('## Installation')
        ->toContain('## Documentation');
});

it('documents installation via Composer', function (): void {
    $content = file_get_contents(__DIR__ . '/../README.md');

    expect($content)->toContain('composer require marko/dev-server');
});

it('documents dev:up, dev:down, and dev:status commands', function (): void {
    $content = file_get_contents(__DIR__ . '/../README.md');

    expect($content)
        ->toContain('dev:up')
        ->toContain('dev:down')
        ->toContain('dev:status');
});

it('documents key commands in quick example', function (): void {
    $content = file_get_contents(__DIR__ . '/../README.md');

    expect($content)
        ->toContain('dev:up')
        ->toContain('dev:down')
        ->toContain('dev:status');
});
