<?php

declare(strict_types=1);

it('has a valid composer.json with type project', function (): void {
    $composerPath = __DIR__ . '/../composer.json';

    expect(file_exists($composerPath))->toBeTrue();

    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['name'])->toBe('marko/skeleton')
        ->and($composer['type'])->toBe('project');
});

it('requires marko/framework', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->toHaveKey('marko/framework');
});

it('requires marko/env', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require'])->toHaveKey('marko/env');
});

it('requires marko/dev-server as a dev dependency', function (): void {
    $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

    expect($composer['require-dev'])->toHaveKey('marko/dev-server');
});

it('has public/index.php using Application::boot() API', function (): void {
    $indexPath = __DIR__ . '/../public/index.php';

    expect(file_exists($indexPath))->toBeTrue();

    $content = file_get_contents($indexPath);

    expect($content)
        ->toContain("require __DIR__ . '/../vendor/autoload.php'")
        ->toContain('Application::boot(dirname(__DIR__))')
        ->toContain('$app->handleRequest()');
});

it('has .env.example with placeholder values', function (): void {
    $envPath = __DIR__ . '/../.env.example';

    expect(file_exists($envPath))->toBeTrue();

    $content = file_get_contents($envPath);

    expect($content)
        ->toContain('APP_ENV=')
        ->toContain('APP_DEBUG=');
});

it('has empty app/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../app/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty modules/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../modules/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty config/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../config/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has empty storage/ directory with .gitkeep', function (): void {
    $gitkeepPath = __DIR__ . '/../storage/.gitkeep';

    expect(file_exists($gitkeepPath))->toBeTrue();
});

it('has a README.md file', function (): void {
    $packagePath = __DIR__ . '/..';

    expect(file_exists($packagePath . '/README.md'))->toBeTrue();
});
