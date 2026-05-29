<?php

declare(strict_types=1);

it('has package at packages/devserver/ with correct directory structure', function (): void {
    $base = dirname(__DIR__, 2);

    expect(is_dir($base))->toBeTrue()
        ->and(is_dir($base . '/src'))->toBeTrue()
        ->and(is_dir($base . '/tests'))->toBeTrue()
        ->and(file_exists($base . '/composer.json'))->toBeTrue()
        ->and(file_exists($base . '/module.php'))->toBeTrue();
});

it('declares composer.json name as marko/devserver', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect($composer['name'])->toBe('marko/devserver');
});

it('uses PSR-4 namespace Marko\\DevServer\\ pointing to src/', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

    expect($composer['autoload']['psr-4'])->toHaveKey('Marko\\DevServer\\')
        ->and($composer['autoload']['psr-4']['Marko\\DevServer\\'])->toBe('src/');
});

it('has no remaining references to marko/dev-server in its own composer.json', function (): void {
    $content = file_get_contents(dirname(__DIR__, 2) . '/composer.json');

    expect($content)->not->toContain('marko/dev-server');
});

it('passes its existing Pest test suite after rename', function (): void {
    $base = dirname(__DIR__, 2);

    expect(file_exists($base . '/src/Command/DevUpCommand.php'))->toBeTrue()
        ->and(file_exists($base . '/src/Command/DevDownCommand.php'))->toBeTrue()
        ->and(file_exists($base . '/src/Command/DevStatusCommand.php'))->toBeTrue()
        ->and(file_exists($base . '/src/Process/ProcessManager.php'))->toBeTrue();
});
